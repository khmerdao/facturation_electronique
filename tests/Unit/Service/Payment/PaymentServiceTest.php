<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Payment;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\PaymentMode;
use App\Repository\PaymentRepository;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceStatusService;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires de PaymentService.
 *
 * Couvre :
 *  - Enregistrement d'un paiement valide
 *  - Blocage si facture non en ACKNOWLEDGED
 *  - Blocage si montant > restant dû
 *  - Transition automatique PAID si solde = 0
 *  - Marquage e-reporting B2C
 *  - Annulation d'un paiement
 */
final class PaymentServiceTest extends TestCase
{
    private PaymentService $service;
    private EntityManagerInterface&MockObject $em;
    private InvoiceStatusService&MockObject $statusService;

    protected function setUp(): void
    {
        $this->em            = $this->createMock(EntityManagerInterface::class);
        $this->statusService = $this->createMock(InvoiceStatusService::class);

        $paymentRepo = $this->createMock(PaymentRepository::class);
        $calculator  = new InvoiceCalculatorService();

        $this->service = new PaymentService(
            $this->em,
            $paymentRepo,
            $calculator,
            $this->statusService,
            new NullLogger(),
        );
    }

    // ── recordOnInvoice() ─────────────────────────────────────────────────────

    #[Test]
    public function record_valid_payment(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $this->em->method('persist');
        $this->em->method('flush');

        $payment = $this->service->recordOnInvoice(
            $invoice,
            $this->paymentData('60.00'),
            new User(),
        );

        self::assertSame('60.00', $payment->getAmount());
        self::assertSame('60.00', $invoice->getAmountPaid());
    }

    #[Test]
    public function record_throws_when_not_acknowledged(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::VALIDATED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/ACKNOWLEDGED/');

        $this->service->recordOnInvoice($invoice, $this->paymentData('50.00'), new User());
    }

    #[Test]
    public function record_throws_when_amount_exceeds_remaining(): void
    {
        $invoice = $this->makeInvoice('100.00', InvoiceStatus::ACKNOWLEDGED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/restant dû/');

        $this->service->recordOnInvoice($invoice, $this->paymentData('150.00'), new User());
    }

    #[Test]
    public function record_throws_when_amount_is_zero(): void
    {
        $invoice = $this->makeInvoice('100.00', InvoiceStatus::ACKNOWLEDGED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/positif/');

        $this->service->recordOnInvoice($invoice, $this->paymentData('0.00'), new User());
    }

    #[Test]
    public function record_triggers_paid_transition_when_fully_paid(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $this->em->method('persist');
        $this->em->method('flush');

        // Le service doit appeler markAsPaid quand le solde est nul
        $this->statusService
            ->expects(self::once())
            ->method('markAsPaid')
            ->with($invoice, self::isInstanceOf(User::class));

        $this->service->recordOnInvoice($invoice, $this->paymentData('120.00'), new User());
    }

    #[Test]
    public function record_does_not_trigger_paid_when_partial(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->statusService->expects(self::never())->method('markAsPaid');

        $this->service->recordOnInvoice($invoice, $this->paymentData('60.00'), new User());
    }

    #[Test]
    public function record_marks_ereporting_required_for_b2c(): void
    {
        // Contact sans SIRET → B2C → ereportingRequired = true
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $contact = new Contact();
        $contact->setName('Client particulier');
        // Pas de SIRET
        $invoice->setContact($contact);

        $this->em->method('persist');
        $this->em->method('flush');

        $payment = $this->service->recordOnInvoice($invoice, $this->paymentData('120.00'), new User());

        self::assertTrue($payment->isEreportingRequired());
    }

    #[Test]
    public function record_does_not_require_ereporting_for_b2b(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $contact = new Contact();
        $contact->setName('ACME SAS');
        $contact->setSiret('35600000000048'); // SIRET présent → B2B
        $invoice->setContact($contact);

        $this->em->method('persist');
        $this->em->method('flush');

        $payment = $this->service->recordOnInvoice($invoice, $this->paymentData('120.00'), new User());

        self::assertFalse($payment->isEreportingRequired());
    }

    #[Test]
    public function record_sets_idempotency_key(): void
    {
        $invoice = $this->makeInvoice('120.00', InvoiceStatus::ACKNOWLEDGED);
        $this->em->method('persist');
        $this->em->method('flush');

        $payment = $this->service->recordOnInvoice($invoice, $this->paymentData('50.00'), new User());

        self::assertNotEmpty($payment->getIdempotencyKey());
    }

    // ── cancel() ─────────────────────────────────────────────────────────────

    #[Test]
    public function cancel_reduces_amount_paid(): void
    {
        $invoice = $this->makeInvoice('200.00', InvoiceStatus::ACKNOWLEDGED);
        $invoice->setAmountPaid('100.00');

        $this->em->method('persist');
        $this->em->method('flush');

        // Créer le paiement via service pour avoir un payment cohérent
        $this->em->method('remove');

        // Simuler le payment à annuler
        $payment = new \App\Entity\Payment();
        $payment->setAmount('100.00');
        $payment->setInvoice($invoice);
        $payment->setTenant($invoice->getTenant());
        $payment->setMode(PaymentMode::VIREMENT);
        $payment->setDate(new \DateTimeImmutable());
        $payment->setDirection(\App\Entity\Enum\PaymentDirection::INCOMING);

        $this->em->expects(self::once())->method('remove')->with($payment);
        $this->em->expects(self::once())->method('flush');

        $this->service->cancel($payment, new User());

        self::assertSame('0.00', $invoice->getAmountPaid());
    }

    #[Test]
    public function cancel_throws_if_invoice_is_paid(): void
    {
        $invoice = $this->makeInvoice('100.00', InvoiceStatus::PAID);

        $payment = new \App\Entity\Payment();
        $payment->setAmount('100.00');
        $payment->setInvoice($invoice);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/avoir/');

        $this->service->cancel($payment, new User());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeInvoice(string $totalTtc, InvoiceStatus $status): Invoice
    {
        $tenant = new Tenant();
        $tenant->setName('Test');

        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setStatus($status);
        $invoice->setTotalTtc($totalTtc);
        $invoice->setTotalHt(bcdiv($totalTtc, '1.20', 2));
        $invoice->setTotalTva(bcsub($totalTtc, bcdiv($totalTtc, '1.20', 2), 2));
        $invoice->setAmountPaid('0.00');
        $invoice->setCurrency('EUR');

        return $invoice;
    }

    private function paymentData(string $amount): array
    {
        return [
            'amount'   => $amount,
            'date'     => new \DateTimeImmutable(),
            'mode'     => PaymentMode::VIREMENT,
            'currency' => 'EUR',
        ];
    }
}
