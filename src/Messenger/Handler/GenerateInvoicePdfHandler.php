<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\GenerateInvoicePdfMessage;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler asynchrone : génère le PDF (et l'XML) d'une facture après sa validation.
 *
 * Dispatché par InvoiceStatusService::validate() via le bus Messenger.
 * S'exécute dans la file "exports" (tâche longue).
 */
#[AsMessageHandler]
final class GenerateInvoicePdfHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PdfGeneratorService $pdfGenerator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateInvoicePdfMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());

        if (!$invoice) {
            $this->logger->warning('pdf.handler.invoice_not_found', [
                'invoice_id' => $message->getInvoiceId(),
            ]);

            return;
        }

        // Générer le PDF et l'XML, uploader sur S3
        $this->pdfGenerator->generate($invoice);
        $this->pdfGenerator->generateXml($invoice);

        $this->em->flush();

        $this->logger->info('pdf.handler.done', [
            'invoice_id' => $message->getInvoiceId(),
            'pdf_key'    => $invoice->getPdfS3Key(),
            'xml_key'    => $invoice->getXmlS3Key(),
        ]);
    }
}
