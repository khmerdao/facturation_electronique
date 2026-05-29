<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Service\Archive\S3StorageService;
use Dompdf\Dompdf;
use Dompdf\Options;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdDocumentReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Génère le PDF d'une facture et l'upload sur S3.
 *
 * Pipeline :
 *   1. Rendre le template Twig → HTML
 *   2. Convertir HTML → PDF via dompdf
 *   3. Si format Factur-X : embeder le XML CII dans le PDF (ZugferdDocumentPdfBuilder)
 *      → produit un PDF/A-3 conforme Factur-X profil EN16931
 *   4. Uploader le PDF sur S3 (bucket invoices)
 *   5. Stocker la clé S3 et le hash SHA-256 sur Invoice
 */
final class PdfGeneratorService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly S3StorageService $s3,
        private readonly InvoiceXmlBuilderService $xmlBuilder,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * Génère le PDF, l'upload sur S3 et met à jour l'entité Invoice.
     * Ne flush pas — l'appelant est responsable du flush.
     *
     * @return string Clé S3 du PDF généré
     */
    public function generate(Invoice $invoice): string
    {
        $this->logger->info('pdf.generating', ['invoice_id' => (string) $invoice->getId()]);

        // ── 1. Rendu HTML via Twig ────────────────────────────────────────────
        $html = $this->twig->render('pdf/invoice.html.twig', [
            'invoice' => $invoice,
            'tenant'  => $invoice->getTenant(),
            'contact' => $invoice->getContact(),
        ]);

        // ── 2. HTML → PDF via dompdf ──────────────────────────────────────────
        $pdfContent = $this->renderPdf($html);

        // ── 3. Embed XML Factur-X si format CII/Factur-X ─────────────────────
        $isFacturX = in_array($invoice->getFormat()->value, ['FACTURX', 'CII'], true);

        if ($isFacturX) {
            $pdfContent = $this->embedFacturX($pdfContent, $invoice);
        }

        // ── 4. Upload S3 ──────────────────────────────────────────────────────
        $s3Key = $this->buildS3Key($invoice, 'pdf');
        $this->s3->upload('invoices', $s3Key, $pdfContent, 'application/pdf');

        // ── 5. Mettre à jour Invoice ──────────────────────────────────────────
        $invoice->setPdfS3Key($s3Key);
        $invoice->setFileHash($this->s3->hashContent($pdfContent));

        $this->logger->info('pdf.generated', [
            'invoice_id' => (string) $invoice->getId(),
            's3_key'     => $s3Key,
            'size_bytes' => strlen($pdfContent),
        ]);

        return $s3Key;
    }

    /**
     * Génère et upload le XML structuré (CII ou UBL) séparément du PDF.
     * Permet de distribuer le XML seul au PDP.
     *
     * @return string Clé S3 du XML généré
     */
    public function generateXml(Invoice $invoice): string
    {
        $xmlContent = $this->xmlBuilder->build($invoice);
        $s3Key      = $this->buildS3Key($invoice, 'xml');

        $this->s3->upload('invoices', $s3Key, $xmlContent, 'application/xml');
        $invoice->setXmlS3Key($s3Key);

        return $s3Key;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Privé
    // ────────────────────────────────────────────────────────────────────────

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $this->projectDir . '/public');
        $options->set('tempDir', $this->projectDir . '/var/tmp/dompdf');

        // Créer le dossier tmp si nécessaire
        if (!is_dir($this->projectDir . '/var/tmp/dompdf')) {
            mkdir($this->projectDir . '/var/tmp/dompdf', 0755, true);
        }

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function embedFacturX(string $pdfContent, Invoice $invoice): string
    {
        // Générer le XML CII
        $xmlContent = $this->xmlBuilder->buildCii($invoice);

        // horstoeko/zugferd : lire le document depuis le XML CII, puis embeder dans le PDF
        $reader = ZugferdDocumentReader::readAndGuessFromContent($xmlContent);

        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfContent($reader, $pdfContent);
        $pdfBuilder->generateDocument();

        return $pdfBuilder->downloadString(
            $invoice->getNumber() ?? (string) $invoice->getId()
        );
    }

    private function buildS3Key(Invoice $invoice, string $extension): string
    {
        $tenantId = (string) $invoice->getTenant()->getId();
        $year     = $invoice->getIssueDate()->format('Y');
        $number   = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $invoice->getNumber() ?? (string) $invoice->getId());

        return sprintf('tenants/%s/invoices/%s/%s.%s', $tenantId, $year, $number, $extension);
    }
}
