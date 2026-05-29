<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceStatusHistory;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Gère les transitions de statut d'une facture (cycle de vie DGFiP).
 *
 * Chaque transition est :
 *   1. Validée contre les transitions autorisées (InvoiceStatus::canTransitionTo)
 *   2. Tracée dans InvoiceStatusHistory (INSERT only — piste d'audit)
 *   3. Flushée dans la même transaction que la facture
 */
final class InvoiceStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceNumberingService $numberingService,
        private readonly InvoiceCalculatorService $calculatorService,
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * DRAFT → VALIDATED
     *
     * - Recalcule les montants
     * - Alloue le numéro définitif depuis la séquence
     * - Copie le snapshot client (immuabilité réglementaire)
     * - Enregistre validatedAt
     *
     * @throws \LogicException si la transition est invalide
     * @throws \RuntimeException si aucune séquence n'est configurée
     */
    public function validate(Invoice $invoice, User $actor, ?string $comment = null): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::VALIDATED);

        // Recalcul final avant gel
        $this->calculatorService->recalculate($invoice);

        // Allouer le numéro depuis la séquence du tenant
        $sequence = $invoice->getSequence()
            ?? $this->sequenceRepository->findDefaultForInvoice($invoice->getTenant());

        if (!$sequence) {
            // Créer la séquence par défaut à la volée
            $sequence = $this->numberingService->createDefaultSequence($invoice->getTenant());
            $this->em->flush(); // persister la séquence avant le lock
        }

        $number = $this->numberingService->allocate($sequence);
        $invoice->setNumber($number);
        $invoice->setSequence($sequence);

        // Snapshot client (art. 242 nonies A — immuabilité)
        $this->copyClientSnapshot($invoice);

        $invoice->setStatus(InvoiceStatus::VALIDATED);
        $invoice->setValidatedAt(new \DateTimeImmutable());

        $this->addHistory($invoice, InvoiceStatus::DRAFT, InvoiceStatus::VALIDATED, $actor, $comment);

        $this->logger->info('invoice.validated', [
            'invoice_id' => (string) $invoice->getId(),
            'number'     => $number,
            'actor'      => $actor->getEmail(),
        ]);
    }

    /**
     * VALIDATED → SENT  (après transmission au PDP/PPF)
     */
    public function markAsSent(Invoice $invoice, ?User $actor = null, ?string $comment = null): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::SENT);

        $invoice->setStatus(InvoiceStatus::SENT);
        $this->addHistory($invoice, InvoiceStatus::VALIDATED, InvoiceStatus::SENT, $actor, $comment);

        $this->logger->info('invoice.sent', ['invoice_id' => (string) $invoice->getId()]);
    }

    /**
     * SENT → ACKNOWLEDGED  (AR positif du PDP destinataire)
     */
    public function markAsAcknowledged(Invoice $invoice, ?string $comment = null): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::ACKNOWLEDGED);

        $invoice->setStatus(InvoiceStatus::ACKNOWLEDGED);
        $this->addHistory($invoice, InvoiceStatus::SENT, InvoiceStatus::ACKNOWLEDGED, null, $comment);

        $this->logger->info('invoice.acknowledged', ['invoice_id' => (string) $invoice->getId()]);
    }

    /**
     * SENT → REJECTED  (rejet PDP)
     */
    public function markAsRejected(Invoice $invoice, ?string $rejectReason = null): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::REJECTED);

        $invoice->setStatus(InvoiceStatus::REJECTED);
        $this->addHistory($invoice, InvoiceStatus::SENT, InvoiceStatus::REJECTED, null, $rejectReason);

        $this->logger->warning('invoice.rejected', [
            'invoice_id' => (string) $invoice->getId(),
            'reason'     => $rejectReason,
        ]);
    }

    /**
     * ACKNOWLEDGED → PAID  (paiement complet enregistré)
     */
    public function markAsPaid(Invoice $invoice, User $actor): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::PAID);

        $invoice->setStatus(InvoiceStatus::PAID);
        $invoice->setPaidAt(new \DateTimeImmutable());
        $this->addHistory($invoice, InvoiceStatus::ACKNOWLEDGED, InvoiceStatus::PAID, $actor);

        $this->logger->info('invoice.paid', ['invoice_id' => (string) $invoice->getId()]);
    }

    /**
     * DRAFT | VALIDATED | ACKNOWLEDGED → CANCELLED
     */
    public function cancel(Invoice $invoice, User $actor, ?string $comment = null): void
    {
        $this->assertCanTransition($invoice, InvoiceStatus::CANCELLED);

        $fromStatus = $invoice->getStatus();
        $invoice->setStatus(InvoiceStatus::CANCELLED);
        $invoice->setDeletedAt(new \DateTimeImmutable());

        $this->addHistory($invoice, $fromStatus, InvoiceStatus::CANCELLED, $actor, $comment);

        $this->logger->info('invoice.cancelled', [
            'invoice_id' => (string) $invoice->getId(),
            'actor'      => $actor->getEmail(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ────────────────────────────────────────────────────────────────────────

    private function assertCanTransition(Invoice $invoice, InvoiceStatus $target): void
    {
        if (!$invoice->getStatus()->canTransitionTo($target)) {
            throw new \LogicException(sprintf(
                'Transition impossible : %s → %s pour la facture %s.',
                $invoice->getStatus()->value,
                $target->value,
                $invoice->getNumber() ?? (string) $invoice->getId(),
            ));
        }
    }

    private function addHistory(
        Invoice $invoice,
        InvoiceStatus $from,
        InvoiceStatus $to,
        ?User $actor,
        ?string $comment = null,
    ): void {
        $entry = new InvoiceStatusHistory();
        $entry->setInvoice($invoice);
        $entry->setFromStatus($from);
        $entry->setToStatus($to);
        $entry->setActor($actor);
        $entry->setComment($comment);

        $invoice->addStatusHistory($entry);
        $this->em->persist($entry);
    }

    /**
     * Copie les données du contact dans le snapshot immuable de la facture.
     * Obligatoire dès la validation (art. 242 nonies A).
     */
    private function copyClientSnapshot(Invoice $invoice): void
    {
        $contact = $invoice->getContact();

        if (!$contact) {
            return;
        }

        $invoice->setClientNameSnapshot($contact->getName());
        $invoice->setClientSiretSnapshot($contact->getSiret());
        $invoice->setClientPdpIdentifier($contact->getPdpIdentifier());
    }
}
