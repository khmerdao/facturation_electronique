<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Entity\ExportJob;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\ExportStatus;
use App\Entity\Enum\ExportType;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Service\Archive\S3StorageService;
use App\Service\Export\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests unitaires de ExportService.
 *
 * Couvre :
 *  - generateFecContent() : en-tête 18 colonnes (art. A47 A-1 CGI)
 *  - generateFecContent() : lignes HT + crédit produit + TVA
 *  - generateFecContent() : ignorance des lignes commentaires
 *  - generateCsvContent() : en-tête colonnes CSV
 *  - generateCsvContent() : une ligne par facture
 *  - fecAmount() : conversion décimale (point → virgule)
 *  - sanitizeFec() : nettoyage pipes et retours chariot
 */
final class ExportServiceTest extends TestCase
{
    private InvoiceRepository&MockObject $invoiceRepository;
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $bus;
    private ExportService $service;

    /** Colonnes FEC obligatoires (art. A47 A-1 CGI) */
    private const FEC_HEADER_COLUMNS = [
        'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
        'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
        'PieceRef', 'PieceDate', 'EcritureLib',
        'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate',
        'Montantdevise', 'Idevise',
    ];

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);
        $this->em                = $this->createMock(EntityManagerInterface::class);
        $this->bus               = $this->createMock(MessageBusInterface::class);
        $s3                      = $this->createMock(S3StorageService::class);

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service = new ExportService(
            em:                  $this->em,
            invoiceRepository:   $this->invoiceRepository,
            s3:                  $s3,
            bus:                 $this->bus,
            logger:              new NullLogger(),
            exportRetentionDays: 30,
        );
    }

    // ── FEC — En-tête ─────────────────────────────────────────────────────

    #[Test]
    public function fec_header_has_18_columns_separated_by_pipe(): void
    {
        $this->invoiceRepository->method('findForFec')->willReturn([]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = explode("\n", trim($content));
        $header  = explode('|', $lines[0]);

        self::assertCount(18, $header, 'Le FEC doit avoir exactement 18 colonnes (art. A47 A-1 CGI)');
    }

    #[Test]
    public function fec_header_contains_all_mandatory_column_names(): void
    {
        $this->invoiceRepository->method('findForFec')->willReturn([]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content     = $this->service->generateFecContent($job);
        $firstLine   = strtok($content, "\n");
        $headerCols  = explode('|', $firstLine);

        foreach (self::FEC_HEADER_COLUMNS as $expected) {
            self::assertContains($expected, $headerCols, "La colonne '$expected' doit être présente dans l'en-tête FEC");
        }
    }

    // ── FEC — Lignes de données ───────────────────────────────────────────

    #[Test]
    public function fec_generates_4_lines_per_invoice_line_with_tva(): void
    {
        // 1 facture avec 1 ligne (TVA > 0) → 4 lignes FEC :
        // 2 lignes HT (débit client + crédit produit) + 2 lignes TVA
        $invoice = $this->makeInvoice('FAC-2026-0001', '100.00', '20.00');
        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = array_filter(explode("\n", trim($content)));
        // Ligne en-tête + 4 lignes de données
        self::assertCount(5, $lines);
    }

    #[Test]
    public function fec_generates_2_lines_per_invoice_line_without_tva(): void
    {
        // TVA = 0 → seulement 2 lignes HT (pas de lignes TVA)
        $invoice = $this->makeInvoice('FAC-2026-0001', '100.00', '0.00');
        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = array_filter(explode("\n", trim($content)));
        self::assertCount(3, $lines); // en-tête + 2 lignes HT
    }

    #[Test]
    public function fec_skips_comment_lines(): void
    {
        $invoice = $this->makeInvoice('FAC-2026-0001', '100.00', '20.00');
        // Ajouter une ligne commentaire
        $commentLine = new InvoiceLine();
        $commentLine->setInvoice($invoice);
        $commentLine->setDescription('Commentaire de section');
        $commentLine->setIsComment(true);
        $commentLine->setQuantity('0');
        $commentLine->setUnitPrice('0');
        $commentLine->setAmountHt('0.00');
        $commentLine->setAmountTva('0.00');
        $commentLine->setUnit('U');
        $commentLine->setTvaRate('20.00');
        $commentLine->setPosition(1);
        $invoice->addLine($commentLine);

        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = array_filter(explode("\n", trim($content)));
        // Même résultat que sans la ligne commentaire : en-tête + 4 lignes
        self::assertCount(5, $lines);
    }

    #[Test]
    public function fec_uses_pipe_separator_in_data_lines(): void
    {
        $invoice = $this->makeInvoice('FAC-2026-0001', '100.00', '20.00');
        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = array_filter(explode("\n", trim($content)));

        // Chaque ligne de données a exactement 17 séparateurs pipe (18 colonnes)
        foreach (array_slice($lines, 1) as $line) {
            $cols = explode('|', $line);
            self::assertCount(18, $cols, "Chaque ligne FEC doit avoir 18 colonnes, ligne : $line");
        }
    }

    // ── FEC — Conversions ─────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideAmountConversions')]
    public function fec_converts_decimal_separator_dot_to_comma(
        string $phpAmount,
        string $expectedFec,
    ): void {
        $invoice = $this->makeInvoice('FAC-TEST', $phpAmount, '0.00');
        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        // La ligne de débit client (3e ligne après l'en-tête)
        $dataLine = explode("\n", trim($content))[1];
        $cols     = explode('|', $dataLine);

        // Colonne Debit (index 11) doit utiliser la virgule
        self::assertStringContainsString(',', $cols[11], 'Le séparateur décimal FEC doit être une virgule');
        self::assertSame($expectedFec, $cols[11]);
    }

    public static function provideAmountConversions(): array
    {
        return [
            'montant entier'     => ['100.00', '100,00'],
            'montant décimal'    => ['1234.56', '1234,56'],
            'petit montant'      => ['0.50', '0,50'],
        ];
    }

    #[Test]
    public function fec_sanitizes_pipe_in_client_name(): void
    {
        $invoice = $this->makeInvoice('FAC-2026-0001', '100.00', '20.00');
        $invoice->setClientNameSnapshot('Client|Pipe|Test');
        $this->invoiceRepository->method('findForFec')->willReturn([$invoice]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);

        // Aucun pipe dans les noms de colonnes (hors séparateurs)
        // → "Client|Pipe|Test" doit devenir "Client Pipe Test"
        self::assertStringNotContainsString('Client|Pipe', $content);
    }

    #[Test]
    public function fec_is_empty_when_no_invoices(): void
    {
        $this->invoiceRepository->method('findForFec')->willReturn([]);
        $job = $this->makeExportJob(ExportType::FEC);

        $content = $this->service->generateFecContent($job);
        $lines   = array_filter(explode("\n", trim($content)));

        self::assertCount(1, $lines, 'Un FEC sans facture ne doit contenir que l\'en-tête');
    }

    // ── CSV ───────────────────────────────────────────────────────────────

    #[Test]
    public function csv_header_contains_required_columns(): void
    {
        $this->invoiceRepository->method('findByFilters')->willReturn([]);
        $job = $this->makeExportJob(ExportType::CSV);

        $content   = $this->service->generateCsvContent($job);
        $firstLine = strtok($content, "\n");

        self::assertStringContainsString('Numéro', $firstLine);
        self::assertStringContainsString('Client', $firstLine);
        self::assertStringContainsString('Total TTC', $firstLine);
        self::assertStringContainsString('Statut', $firstLine);
    }

    #[Test]
    public function csv_generates_one_row_per_invoice(): void
    {
        $invoices = [
            $this->makeInvoice('FAC-2026-0001', '100.00', '20.00'),
            $this->makeInvoice('FAC-2026-0002', '200.00', '40.00'),
            $this->makeInvoice('FAC-2026-0003', '50.00', '10.00'),
        ];
        $this->invoiceRepository->method('findByFilters')->willReturn($invoices);
        $job = $this->makeExportJob(ExportType::CSV);

        $content = $this->service->generateCsvContent($job);
        $lines   = array_filter(explode("\n", trim($content)));

        self::assertCount(4, $lines, 'En-tête + 3 factures = 4 lignes CSV');
    }

    #[Test]
    public function csv_uses_semicolon_separator(): void
    {
        $this->invoiceRepository->method('findByFilters')->willReturn([
            $this->makeInvoice('FAC-2026-0001', '100.00', '20.00'),
        ]);
        $job = $this->makeExportJob(ExportType::CSV);

        $content   = $this->service->generateCsvContent($job);
        $firstLine = strtok($content, "\n");

        self::assertStringContainsString(';', $firstLine, 'Le CSV doit utiliser le point-virgule comme séparateur');
    }

    #[Test]
    public function csv_converts_decimal_dot_to_comma(): void
    {
        $this->invoiceRepository->method('findByFilters')->willReturn([
            $this->makeInvoice('FAC-2026-0001', '1234.56', '246.91'),
        ]);
        $job = $this->makeExportJob(ExportType::CSV);

        $content = $this->service->generateCsvContent($job);

        self::assertStringContainsString('1234,56', $content);
        self::assertStringNotContainsString('1234.56', $content);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeExportJob(ExportType $type): ExportJob
    {
        $tenant = new Tenant();
        $tenant->setName('Test Corp');

        $job = new ExportJob();
        $job->setTenant($tenant);
        $job->setType($type);
        $job->setStatus(ExportStatus::PROCESSING);
        $job->setParams([
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);
        $job->setExpiresAt(new \DateTimeImmutable('+30 days'));

        return $job;
    }

    private function makeInvoice(string $number, string $amountHt, string $amountTva): Invoice
    {
        $tenant  = new Tenant();
        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setNumber($number);
        $invoice->setStatus(InvoiceStatus::PAID);
        $invoice->setFormat(InvoiceFormat::FACTURX);
        $invoice->setType(InvoiceType::INVOICE);
        $invoice->setCurrency('EUR');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-06-15'));
        $invoice->setClientNameSnapshot('Client Test SARL');
        $invoice->setTotalHt($amountHt);
        $invoice->setTotalTva($amountTva);
        $invoice->setTotalTtc(bcadd($amountHt, $amountTva, 2));
        $invoice->setAmountPaid(bcadd($amountHt, $amountTva, 2));

        $line = new InvoiceLine();
        $line->setInvoice($invoice);
        $line->setDescription('Prestation de test');
        $line->setQuantity('1');
        $line->setUnit('U');
        $line->setUnitPrice($amountHt);
        $line->setDiscount('0.00');
        $line->setTvaRate($amountTva === '0.00' ? '0.00' : '20.00');
        $line->setAmountHt($amountHt);
        $line->setAmountTva($amountTva);
        $line->setPosition(0);
        $line->setIsComment(false);
        $invoice->addLine($line);

        return $invoice;
    }
}
