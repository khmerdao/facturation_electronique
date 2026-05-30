<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Repository\InvoiceSequenceRepository;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceNumberingService;
use App\Service\Invoice\InvoiceStatusService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Tests unitaires de InvoiceStatusService.
 *
 * Couvre les transitions DGFiP :
 *  DRAFT → VALIDATED → SENT → ACKNOWLEDGED → PAID
 *                     ↓                ↓
 *                  REJECTED        CANCELLED
 */
final class InvoiceStatusServiceTest extends TestCase
{
    private InvoiceStatusService $service;
    private EntityManagerInterface&MockObject $em;
    private InvoiceSequenceRepository&MockObject $seqRepo;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->seqRepo = $this->createMock(InvoiceSequenceRepository::class);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->method('release')->willReturn(null);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $this->seqRepo->method('lockForUpdate')->willReturnArgument(0);

        $numbering  = new InvoiceNumberingService($this->em, $this->seqRepo, $lockFactory, new NullLogger());
        $calculator = new InvoiceCalculatorService();

        $this->service = new InvoiceStatusService(
            $this->em,
            $numbering,
            $calculator,
            $this->seqRepo,
            new NullLogger(),
        );
    }

    // ── validate() ───────────────────────────────────────────────────────────

    #[Test]
    public function validate_transitions_draft_to_validated(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $this->configureSequence($invoice);
        $this->em->method('flush');

        $this->service->validate($invoice, new User());

        self::assertSame(InvoiceStatus::VALIDATED, $invoice->getStatus());
        self::assertNotNull($invoice->getNumber());
        self::assertNotNull($invoice->getValidatedAt());
    }

    #[Test]
    public function validate_copies_client_snapshot(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $contact = new Contact();
        $contact->setName('ACME SAS');
        $contact->setSiret('35600000000048');
        $invoice->setContact($contact);

        $this->configureSequence($invoice);
        $this->em->method('flush');

        $this->service->validate($invoice, new User());

        self::assertSame('ACME SAS', $invoice->getClientNameSnapshot());
        self::assertSame('35600000000048', $invoice->getClientSiretSnapshot());
    }

    #[Test]
    public function validate_throws_on_non_draft(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::VALIDATED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/VALIDATED.*VALIDATED/');

        $this->service->validate($invoice, new User());
    }

    #[Test]
    public function validate_creates_history_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $this->configureSequence($invoice);
        $this->em->method('flush');
        $this->em->expects(self::atLeastOnce())->method('persist');

        $this->service->validate($invoice, new User());

        $history = $invoice->getStatusHistory()->toArray();
        self::assertCount(1, $history);
        self::assertSame(InvoiceStatus::DRAFT,     $history[0]->getFromStatus());
        self::assertSame(InvoiceStatus::VALIDATED,  $history[0]->getToStatus());
    }

    // ── markAsSent() ──────────────────────────────────────────────────────────

    #[Test]
    public function markAsSent_transitions_validated_to_sent(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::VALIDATED);

        $this->service->markAsSent($invoice);

        self::assertSame(InvoiceStatus::SENT, $invoice->getStatus());
    }

    #[Test]
    public function markAsSent_throws_on_draft(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);

        $this->expectException(\LogicException::class);

        $this->service->markAsSent($invoice);
    }

    // ── markAsAcknowledged() ──────────────────────────────────────────────────

    #[Test]
    public function markAsAcknowledged_transitions_sent_to_acknowledged(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::SENT);

        $this->service->markAsAcknowledged($invoice, 'AR positif PDP');

        self::assertSame(InvoiceStatus::ACKNOWLEDGED, $invoice->getStatus());
        $history = $invoice->getStatusHistory()->last();
        self::assertSame('AR positif PDP', $history->getComment());
    }

    // ── markAsRejected() ─────────────────────────────────────────────────────

    #[Test]
    public function markAsRejected_transitions_sent_to_rejected(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::SENT);

        $this->service->markAsRejected($invoice, 'Format XML invalide');

        self::assertSame(InvoiceStatus::REJECTED, $invoice->getStatus());
    }

    // ── markAsPaid() ──────────────────────────────────────────────────────────

    #[Test]
    public function markAsPaid_transitions_acknowledged_to_paid(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::ACKNOWLEDGED);
        $user    = new User();

        $this->service->markAsPaid($invoice, $user);

        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        self::assertNotNull($invoice->getPaidAt());
    }

    #[Test]
    public function markAsPaid_throws_on_draft(): void
    {
        $this->expectException(\LogicException::class);

        $this->service->markAsPaid($this->makeInvoice(InvoiceStatus::DRAFT), new User());
    }

    // ── cancel() ─────────────────────────────────────────────────────────────

    #[Test]
    public function cancel_transitions_draft_to_cancelled(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);
        $user    = new User();

        $this->service->cancel($invoice, $user, 'Annulation test');

        self::assertSame(InvoiceStatus::CANCELLED, $invoice->getStatus());
        self::assertNotNull($invoice->getDeletedAt());
    }

    #[Test]
    public function cancel_throws_on_paid(): void
    {
        $this->expectException(\LogicException::class);

        $this->service->cancel($this->makeInvoice(InvoiceStatus::PAID), new User());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeInvoice(InvoiceStatus $status): Invoice
    {
        $tenant = new Tenant();
        $tenant->setName('Test Corp');

        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setStatus($status);
        $invoice->setTotalHt('100.00');
        $invoice->setTotalTva('20.00');
        $invoice->setTotalTtc('120.00');

        return $invoice;
    }

    private function configureSequence(Invoice $invoice): void
    {
        $seq = new InvoiceSequence();
        $seq->setPrefix('FAC');
        $seq->setYearFormat('AAAA');
        $seq->setSeparator('-');
        $seq->setPadding(4);
        $seq->setNextNumber(1);
        $seq->setStartNumber(1);
        $seq->setName('Test');

        $this->seqRepo
            ->method('findDefaultForInvoice')
            ->willReturn($seq);

        $invoice->setSequence($seq);
    }
}
