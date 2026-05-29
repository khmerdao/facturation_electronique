<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\PaymentDirection;
use App\Entity\Enum\PaymentMode;
use App\Repository\PaymentRepository;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Enregistrement des paiements sur factures émises.
 *
 * Gère :
 *   - La création de l'entité Payment avec idempotencyKey
 *   - La mise à jour de Invoice::amountPaid
 *   - La transition automatique vers PAID si amountPaid >= totalTtc
 *   - Le marquage ereportingRequired pour les paiements B2C/international
 */
final class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentRepository $paymentRepository,
        private readonly InvoiceCalculatorService $calculatorService,
        private readonly InvoiceStatusService $statusService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Enregistre un paiement sur une facture émise.
     *
     * @param array{
     *   amount: string,
     *   date: \DateTimeImmutable,
     *   mode: PaymentMode,
     *   reference?: string|null,
     *   notes?: string|null,
     *   currency?: string,
     * } $data
     *
     * @throws \LogicException si la facture n'est pas en statut ACKNOWLEDGED
     * @throws \LogicException si le montant dépasse le restant dû
     */
    public function recordOnInvoice(Invoice $invoice, array $data, User $actor): Payment
    {
        if (!$invoice->getStatus()->canRecordPayment()) {
            throw new \LogicException(sprintf(
                'Impossible d\'enregistrer un paiement sur une facture en statut "%s". '
                . 'La facture doit être en statut ACKNOWLEDGED.',
                $invoice->getStatus()->value,
            ));
        }

        $amount         = $data['amount'];
        $remainingDue   = $this->calculatorService->getRemainingDue($invoice);

        // Vérifier que le montant ne dépasse pas le restant dû
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \LogicException('Le montant du paiement doit être positif.');
        }

        if (bccomp($amount, bcadd($remainingDue, '0.01', 2), 2) > 0) {
            throw new \LogicException(sprintf(
                'Le montant du paiement (%.2f €) dépasse le restant dû (%.2f €).',
                (float) $amount,
                (float) $remainingDue,
            ));
        }

        // Idempotency key : évite les doubles enregistrements
        $idempotencyKey = (string) Uuid::v4();

        $payment = new Payment();
        $payment->setTenant($invoice->getTenant());
        $payment->setInvoice($invoice);
        $payment->setRecordedBy($actor);
        $payment->setDirection(PaymentDirection::INCOMING);
        $payment->setAmount($amount);
        $payment->setCurrency($data['currency'] ?? $invoice->getCurrency());
        $payment->setDate($data['date']);
        $payment->setMode($data['mode']); // setModeDgfipCode est appelé automatiquement dans setMode()
        $payment->setReference($data['reference'] ?? null);
        $payment->setNotes($data['notes'] ?? null);
        $payment->setIdempotencyKey($idempotencyKey);

        // E-reporting requis pour les paiements B2C (non B2B)
        $contact  = $invoice->getContact();
        $isB2B    = $contact && $contact->getSiret();
        $payment->setEreportingRequired(!$isB2B);

        // Mettre à jour amountPaid sur la facture
        $newAmountPaid = bcadd($invoice->getAmountPaid(), $amount, 2);
        $invoice->setAmountPaid($newAmountPaid);

        $this->em->persist($payment);

        // Transition automatique PAID si facture entièrement réglée
        if ($this->calculatorService->isFullyPaid($invoice)) {
            $this->statusService->markAsPaid($invoice, $actor);
        }

        $this->em->flush();

        $this->logger->info('payment.recorded', [
            'payment_id' => (string) $payment->getId(),
            'invoice_id' => (string) $invoice->getId(),
            'amount'     => $amount,
            'actor'      => $actor->getEmail(),
        ]);

        return $payment;
    }

    /**
     * Annule un paiement et met à jour amountPaid sur la facture.
     *
     * @throws \LogicException si la facture est déjà payée
     */
    public function cancel(Payment $payment, User $actor): void
    {
        $invoice = $payment->getInvoice();

        if ($invoice && $invoice->getStatus() === InvoiceStatus::PAID) {
            throw new \LogicException(
                'Impossible d\'annuler un paiement sur une facture déjà soldée. '
                . 'Émettez un avoir à la place.'
            );
        }

        if ($invoice) {
            $newAmountPaid = bcsub($invoice->getAmountPaid(), $payment->getAmount(), 2);
            $newAmountPaid = bccomp($newAmountPaid, '0', 2) < 0 ? '0.00' : $newAmountPaid;
            $invoice->setAmountPaid($newAmountPaid);
        }

        $this->em->remove($payment);
        $this->em->flush();

        $this->logger->info('payment.cancelled', [
            'payment_id' => (string) $payment->getId(),
            'actor'      => $actor->getEmail(),
        ]);
    }
}
