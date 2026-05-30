<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Entity\Enum\PdpTransmissionStatus;
use App\Messenger\Message\ProcessPdpWebhookMessage;
use App\Repository\PdpTransmissionRepository;
use App\Repository\PdpWebhookLogRepository;
use App\Service\Invoice\InvoiceStatusService;
use App\Service\Notification\NotificationService;
use App\Entity\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Traite les événements entrants depuis le PDP/PPF.
 *
 * Événements supportés :
 *   - invoice.acknowledged  → facture acceptée (SENT → ACKNOWLEDGED)
 *   - invoice.rejected      → facture rejetée (SENT → REJECTED)
 *   - invoice.received      → nouvelle facture reçue (crée ReceivedInvoice)
 */
#[AsMessageHandler]
final class ProcessPdpWebhookHandler
{
    public function __construct(
        private readonly PdpWebhookLogRepository $webhookLogRepository,
        private readonly PdpTransmissionRepository $transmissionRepository,
        private readonly InvoiceStatusService $statusService,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessPdpWebhookMessage $message): void
    {
        $log = $this->webhookLogRepository->find($message->getWebhookLogId());

        if (!$log) {
            $this->logger->warning('pdp.webhook.log_not_found', [
                'log_id' => $message->getWebhookLogId(),
            ]);

            return;
        }

        if ($log->isProcessed()) {
            return; // Idempotence
        }

        $payload   = $log->getPayload();
        $eventType = $log->getEventType();
        $tenant    = $log->getTenant();

        try {
            match ($eventType) {
                'invoice.acknowledged' => $this->handleAcknowledged($payload, $tenant),
                'invoice.rejected'     => $this->handleRejected($payload, $tenant),
                default                => $this->logger->info('pdp.webhook.unknown_event', ['event' => $eventType]),
            };

            $log->setProcessed(true);
            $log->setProcessedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            $log->setProcessingError($e->getMessage());
            $this->em->flush();

            $this->logger->error('pdp.webhook.processing_error', [
                'event_id' => $log->getEventId(),
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function handleAcknowledged(array $payload, $tenant): void
    {
        $externalId  = $payload['invoiceId'] ?? null;
        $transmission = $this->transmissionRepository->findByExternalId($externalId);

        if (!$transmission) {
            $this->logger->warning('pdp.webhook.transmission_not_found', ['external_id' => $externalId]);

            return;
        }

        $transmission->setStatus(PdpTransmissionStatus::ACKNOWLEDGED);
        $transmission->setAcknowledgedAt(new \DateTimeImmutable());

        $invoice = $transmission->getInvoice();

        if ($invoice && $invoice->getStatus() === InvoiceStatus::SENT) {
            $this->statusService->markAsAcknowledged($invoice, 'AR positif reçu via webhook');

            $this->notificationService->success(
                $tenant,
                'invoice.acknowledged',
                'Facture acceptée',
                sprintf('La facture %s a été acceptée par le destinataire.', $invoice->getNumber()),
            );
        }

        $this->em->flush();
    }

    private function handleRejected(array $payload, $tenant): void
    {
        $externalId   = $payload['invoiceId'] ?? null;
        $transmission = $this->transmissionRepository->findByExternalId($externalId);

        if (!$transmission) {
            return;
        }

        $rejectCode   = $payload['rejectCode'] ?? 'UNKNOWN';
        $rejectReason = $payload['rejectReason'] ?? null;

        $transmission->setStatus(PdpTransmissionStatus::REJECTED);
        $transmission->setRejectCode($rejectCode);
        $transmission->setRejectReason($rejectReason);

        $invoice = $transmission->getInvoice();

        if ($invoice && $invoice->getStatus() === InvoiceStatus::SENT) {
            $this->statusService->markAsRejected($invoice, $rejectReason);

            $this->notificationService->alert(
                $tenant,
                'invoice.rejected',
                'Facture rejetée',
                sprintf('La facture %s a été rejetée : %s', $invoice->getNumber(), $rejectReason ?? $rejectCode),
            );
        }

        $this->em->flush();
    }
}
