<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Repository\InvoiceSequenceRepository;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceDuplicateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de InvoiceDuplicateService.
 *
 * Couvre :
 *  - duplicate() : copie DRAFT sans numéro, lignes copiées, sujet préfixé
 *  - duplicate() : date d'échéance calculée relativement
 *  - duplicate() : les paiements ne sont PAS copiés
 *  - createCreditNote() : type CREDIT_NOTE, lien creditNoteFor
 *  - createCreditNote() : bloqué si statut incompatible
 *  - createCreditNote() : montants identiques à l'original
 */
final class InvoiceDuplicateServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private InvoiceSequenceRepository&MockObject $sequenceRepository;
    private InvoiceDuplicateService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->sequenceRepository = $this->createMock(InvoiceSequenceRepository::class);

        $this->service = new InvoiceDuplicateService(
            $this->em,
            new InvoiceCalculatorService(),
            $this->sequenceRepository,
        );
    }

    // ── duplicate() ───────────────────────────────────────────────────────

    #[Test]
    public function duplicate_creates_draft_copy(): void
    {
        $invoice = $this->makeValidatedInvoice();
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertSame(InvoiceStatus::DRAFT, $copy->getStatus());
        self::assertNull($copy->getNumber(), 'La copie ne doit pas avoir de numéro');
    }

    #[Test]
    public function duplicate_does_not_share_id_with_original(): void
    {
        $invoice = $this->makeValidatedInvoice();
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertNotSame($invoice, $copy);
    }

    #[Test]
    public function duplicate_copies_all_lines(): void
    {
        $invoice = $this->makeValidatedInvoice(linesCount: 3);
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertCount(3, $copy->getLines(), 'La copie doit avoir le même nombre de lignes');
    }

    #[Test]
    public function duplicate_prefixes_subject_with_copy_label(): void
    {
        $invoice = $this->makeValidatedInvoice();
        $invoice->setSubject('Prestation de développement');
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertStringStartsWith('[Copie]', $copy->getSubject());
        self::assertStringContainsString('Prestation de développement', $copy->getSubject());
    }

    #[Test]
    public function duplicate_preserves_contact_and_currency(): void
    {
        $invoice = $this->makeValidatedInvoice();
        $invoice->setCurrency('USD');
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertSame('USD', $copy->getCurrency());
        self::assertSame($invoice->getTenant(), $copy->getTenant());
    }

    #[Test]
    public function duplicate_recalculates_totals(): void
    {
        $invoice = $this->makeValidatedInvoice(linesCount: 1);
        // Ligne avec 2 × 100 HT, 20% TVA → HT=200, TVA=40, TTC=240
        $invoice->getLines()->first()->setQuantity('2');
        $invoice->getLines()->first()->setUnitPrice('100.00');
        $invoice->getLines()->first()->setTvaRate('20.00');
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertSame('200.00', $copy->getTotalHt());
        self::assertSame('240.00', $copy->getTotalTtc());
    }

    #[Test]
    public function duplicate_does_not_copy_payment_amount(): void
    {
        $invoice = $this->makeValidatedInvoice();
        $invoice->setAmountPaid('120.00');
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertSame('0.00', $copy->getAmountPaid(), 'Les paiements ne doivent pas être copiés');
    }

    #[Test]
    public function duplicate_calculates_due_date_relatively(): void
    {
        $invoice    = $this->makeValidatedInvoice();
        $issueDate  = new \DateTimeImmutable('2026-01-01');
        $dueDate    = new \DateTimeImmutable('2026-02-01'); // +31 jours
        $invoice->setIssueDate($issueDate);
        $invoice->setDueDate($dueDate);
        $this->em->method('persist');

        $copy = $this->service->duplicate($invoice, new User());

        self::assertNotNull($copy->getDueDate());
        // L'intervalle entre issueDate et dueDate (31j) doit être préservé
        $interval = $copy->getIssueDate()->diff($copy->getDueDate());
        self::assertSame(31, (int) $interval->days);
    }

    #[Test]
    public function duplicate_persists_copy_and_lines(): void
    {
        $invoice = $this->makeValidatedInvoice(linesCount: 2);

        // persist appelé au moins 3 fois : la copie + 2 lignes
        $this->em->expects(self::atLeast(3))->method('persist');

        $this->service->duplicate($invoice, new User());
    }

    // ── createCreditNote() ────────────────────────────────────────────────

    #[Test]
    public function create_credit_note_sets_type_credit_note(): void
    {
        $invoice = $this->makeAcknowledgedInvoice();
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User(), 'Erreur de facturation');

        self::assertSame(InvoiceType::CREDIT_NOTE, $cn->getType());
    }

    #[Test]
    public function create_credit_note_links_to_original(): void
    {
        $invoice = $this->makeAcknowledgedInvoice();
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User());

        self::assertSame($invoice, $cn->getCreditNoteFor());
    }

    #[Test]
    public function create_credit_note_is_draft(): void
    {
        $invoice = $this->makeAcknowledgedInvoice();
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User());

        self::assertSame(InvoiceStatus::DRAFT, $cn->getStatus());
    }

    #[Test]
    public function create_credit_note_copies_lines_and_amounts(): void
    {
        $invoice = $this->makeAcknowledgedInvoice(linesCount: 2);
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User());

        self::assertCount(2, $cn->getLines());
        self::assertSame($invoice->getTotalHt(), $cn->getTotalHt());
        self::assertSame($invoice->getTotalTtc(), $cn->getTotalTtc());
    }

    #[Test]
    public function create_credit_note_uses_reason_as_subject(): void
    {
        $invoice = $this->makeAcknowledgedInvoice();
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User(), 'Retour marchandise');

        self::assertSame('Retour marchandise', $cn->getSubject());
    }

    #[Test]
    public function create_credit_note_throws_on_draft_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::DRAFT);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/avoir.*DRAFT/i');

        $this->service->createCreditNote($invoice, new User());
    }

    #[Test]
    public function create_credit_note_throws_on_sent_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::SENT);

        $this->expectException(\LogicException::class);

        $this->service->createCreditNote($invoice, new User());
    }

    #[Test]
    public function create_credit_note_allowed_on_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::PAID);
        $this->em->method('persist');
        $this->sequenceRepository->method('findDefaultForCreditNote')->willReturn(null);

        $cn = $this->service->createCreditNote($invoice, new User());

        self::assertSame(InvoiceType::CREDIT_NOTE, $cn->getType());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeInvoice(InvoiceStatus $status, int $linesCount = 1): Invoice
    {
        $tenant  = new Tenant();
        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setStatus($status);
        $invoice->setFormat(InvoiceFormat::FACTURX);
        $invoice->setType(InvoiceType::INVOICE);
        $invoice->setCurrency('EUR');
        $invoice->setIssueDate(new \DateTimeImmutable());
        $invoice->setTotalHt('100.00');
        $invoice->setTotalTva('20.00');
        $invoice->setTotalTtc('120.00');
        $invoice->setAmountPaid('0.00');

        for ($i = 0; $i < $linesCount; $i++) {
            $line = new InvoiceLine();
            $line->setInvoice($invoice);
            $line->setDescription('Ligne ' . ($i + 1));
            $line->setQuantity('1');
            $line->setUnit('U');
            $line->setUnitPrice('100.00');
            $line->setDiscount('0.00');
            $line->setTvaRate('20.00');
            $line->setPosition($i);
            $invoice->addLine($line);
        }

        return $invoice;
    }

    private function makeValidatedInvoice(int $linesCount = 1): Invoice
    {
        $invoice = $this->makeInvoice(InvoiceStatus::VALIDATED, $linesCount);
        $invoice->setNumber('FAC-2026-0001');
        return $invoice;
    }

    private function makeAcknowledgedInvoice(int $linesCount = 1): Invoice
    {
        $invoice = $this->makeInvoice(InvoiceStatus::ACKNOWLEDGED, $linesCount);
        $invoice->setNumber('FAC-2026-0001');
        return $invoice;
    }
}
