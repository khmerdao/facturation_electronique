<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\SendInvoiceToPdpMessage;
use App\Repository\InvoiceRepository;
use App\Service\PDP\PdpDispatchService;
use App\Service\Invoice\PdfGeneratorService;
use App\Entity\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler haute priorité (file pdp_urgent) :
 * transmet une facture au PDP/PPF après validation.
 *
 * Si le PDF/XML n'est pas encore généré (possible race condition),
 * les génère à la volée avant l'envoi.
 */
#[AsMessageHandler]
final class SendInvoiceToPdpHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PdpDispatchService $pdpDispatch,
        private readonly PdfGeneratorService $pdfGenerator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendInvoiceToPdpMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());

        if (!$invoice) {
            $this->logger->warning('pdp.handler.invoice_not_found', [
                'invoice_id' => $message->getInvoiceId(),
            ]);

            return;
        }

        // Vérifier que la facture est toujours en statut envoyable
        if (!in_array($invoice->getStatus(), [InvoiceStatus::VALIDATED, InvoiceStatus::REJECTED], true)) {
            $this->logger->info('pdp.handler.skip_not_validated', [
                'invoice_id' => $message->getInvoiceId(),
                'status'     => $invoice->getStatus()->value,
            ]);

            return;
        }

        // Générer PDF/XML si absents (race condition avec GenerateInvoicePdfHandler)
        if (!$invoice->getPdfS3Key() || !$invoice->getXmlS3Key()) {
            $this->logger->info('pdp.handler.generating_files_on_the_fly', [
                'invoice_id' => $message->getInvoiceId(),
            ]);
            $this->pdfGenerator->generate($invoice);
            $this->pdfGenerator->generateXml($invoice);
            $this->em->flush();
        }

        // Transmettre au PDP/PPF
        $transmission = $this->pdpDispatch->dispatch($invoice);

        $this->logger->info('pdp.handler.done', [
            'invoice_id'      => $message->getInvoiceId(),
            'transmission_id' => (string) $transmission->getId(),
            'status'          => $transmission->getStatus()->value,
        ]);
    }
}
