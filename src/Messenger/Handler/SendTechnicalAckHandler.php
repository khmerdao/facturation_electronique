<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\SendTechnicalAckMessage;
use App\Repository\ReceivedInvoiceRepository;
use App\Service\PDP\PdpDispatchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoie l'acquittement technique (AR1) au PDP émetteur d'une facture reçue.
 *
 * Obligatoire dans les 5 jours ouvrés (art. R.123-208-12 C.com.).
 * L'AR technique confirme la bonne réception — il ne constitue pas
 * une validation métier de la facture.
 */
#[AsMessageHandler]
final class SendTechnicalAckHandler
{
    public function __construct(
        private readonly ReceivedInvoiceRepository $receivedInvoiceRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendTechnicalAckMessage $message): void
    {
        $receivedInvoice = $this->receivedInvoiceRepository->find($message->getReceivedInvoiceId());

        if (!$receivedInvoice) {
            $this->logger->warning('ack.handler.not_found', [
                'received_invoice_id' => $message->getReceivedInvoiceId(),
            ]);

            return;
        }

        // Éviter les doublons
        if ($receivedInvoice->getTechnicalAckSentAt()) {
            $this->logger->info('ack.handler.already_sent', [
                'received_invoice_id' => $message->getReceivedInvoiceId(),
            ]);

            return;
        }

        $pdpConfig = $receivedInvoice->getTenant()->getPdpConfig();

        // Envoyer l'AR via le PDP ou le PPF
        try {
            if ($pdpConfig->isConfigured() && $pdpConfig->getEndpointUrl()) {
                $this->httpClient->request('POST', rtrim($pdpConfig->getEndpointUrl(), '/') . '/ack', [
                    'json' => [
                        'invoiceId'  => $receivedInvoice->getExternalPdpId(),
                        'ackType'    => 'TECHNICAL',
                        'emitterId'  => $pdpConfig->getEmitterId(),
                        'receivedAt' => $receivedInvoice->getReceivedAt()->format(\DateTimeInterface::ATOM),
                    ],
                    'timeout' => 15,
                ]);
            }

            $receivedInvoice->setTechnicalAckSentAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->logger->info('ack.handler.sent', [
                'received_invoice_id' => $message->getReceivedInvoiceId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ack.handler.error', [
                'received_invoice_id' => $message->getReceivedInvoiceId(),
                'error'               => $e->getMessage(),
            ]);

            // Relever l'exception pour déclencher le retry Messenger
            throw $e;
        }
    }
}
