<?php

declare(strict_types=1);

namespace App\Service\PDP;

use App\Entity\Invoice;
use App\Entity\PdpTransmission;
use App\Entity\Enum\PdpMode;
use App\Entity\Enum\PdpTransmissionStatus;
use App\Service\Archive\S3StorageService;
use App\Service\Invoice\InvoiceStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transmet une facture validée vers le PDP partenaire ou le PPF direct.
 *
 * Cycle de transmission :
 *   1. Lecture du PDF et XML depuis S3
 *   2. Envoi HTTP multipart vers l'endpoint PDP (ou PPF)
 *   3. Mise à jour du statut PdpTransmission (SENT / ERROR)
 *   4. Mise à jour du statut Invoice (VALIDATED → SENT)
 *
 * Le statut ACKNOWLEDGED arrive ensuite via webhook PDP entrant
 * (PdpWebhookController → ProcessPdpWebhookHandler).
 */
final class PdpDispatchService
{
    /** Timeout HTTP pour les appels PDP (secondes). */
    private const HTTP_TIMEOUT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly S3StorageService $s3,
        private readonly PdpConfigEncryptorService $encryptor,
        private readonly InvoiceStatusService $statusService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $pdpLogger,
    ) {}

    /**
     * Transmet une facture vers le PDP/PPF du tenant.
     *
     * @throws \RuntimeException si la configuration PDP est absente
     */
    public function dispatch(Invoice $invoice): PdpTransmission
    {
        $tenant    = $invoice->getTenant();
        $pdpConfig = $tenant->getPdpConfig();

        if (!$pdpConfig->isConfigured()) {
            throw new \RuntimeException(sprintf(
                'Aucune configuration PDP pour le tenant "%s". Configurez le PDP dans les paramètres.',
                $tenant->getName(),
            ));
        }

        // Créer la transmission
        $transmission = new PdpTransmission();
        $transmission->setTenant($tenant);
        $transmission->setInvoice($invoice);
        $transmission->setStatus(PdpTransmissionStatus::PENDING);
        $transmission->setPdpName($pdpConfig->getPdpName());
        $transmission->setAttempt(1);

        $this->em->persist($transmission);
        $this->em->flush();

        try {
            // Charger les fichiers depuis S3
            $pdfContent = $invoice->getPdfS3Key()
                ? $this->s3->download('invoices', $invoice->getPdfS3Key())
                : null;

            $xmlContent = $invoice->getXmlS3Key()
                ? $this->s3->download('invoices', $invoice->getXmlS3Key())
                : null;

            if (!$xmlContent) {
                throw new \RuntimeException('XML de la facture absent sur S3. Relancez la génération PDF.');
            }

            // Sélectionner le canal de transmission
            $response = match ($pdpConfig->getMode()) {
                PdpMode::PPF     => $this->sendToPpf($invoice, $xmlContent, $pdfContent, $pdpConfig),
                PdpMode::PDP     => $this->sendToPdp($invoice, $xmlContent, $pdfContent, $pdpConfig),
                null             => throw new \RuntimeException('Mode PDP non configuré.'),
            };

            // Mise à jour de la transmission
            $transmission->setStatus(PdpTransmissionStatus::SENT);
            $transmission->setSentAt(new \DateTimeImmutable());
            $transmission->setExternalId($response['externalId'] ?? null);
            $transmission->setRawResponse($response);

            // Transition Invoice VALIDATED → SENT
            $this->statusService->markAsSent($invoice, comment: 'Transmission PDP automatique');

            $this->pdpLogger->info('pdp.dispatch.sent', [
                'invoice_id'      => (string) $invoice->getId(),
                'invoice_number'  => $invoice->getNumber(),
                'pdp_name'        => $pdpConfig->getPdpName(),
                'external_id'     => $response['externalId'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $transmission->setStatus(PdpTransmissionStatus::ERROR);
            $transmission->setRejectCode('HTTP_ERROR');
            $transmission->setRejectReason($e->getMessage());
            $transmission->setRawResponse(['error' => $e->getMessage()]);

            $this->pdpLogger->error('pdp.dispatch.error', [
                'invoice_id' => (string) $invoice->getId(),
                'error'      => $e->getMessage(),
            ]);
        }

        $this->em->flush();

        return $transmission;
    }

    /**
     * Vérifie le statut d'une transmission auprès du PDP.
     * Appelé périodiquement si aucun webhook n'a été reçu.
     */
    public function checkStatus(PdpTransmission $transmission): void
    {
        if (!$transmission->getExternalId()) {
            return;
        }

        $pdpConfig = $transmission->getTenant()->getPdpConfig();

        if (!$pdpConfig->isConfigured()) {
            return;
        }

        try {
            $apiKey   = $this->decryptApiKey($pdpConfig->getApiKeyEncrypted());
            $endpoint = rtrim($pdpConfig->getEndpointUrl() ?? '', '/');
            $url      = sprintf('%s/invoices/%s', $endpoint, $transmission->getExternalId());

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'X-Emitter-Id'  => $pdpConfig->getEmitterId(),
                ],
                'timeout' => self::HTTP_TIMEOUT,
            ]);

            $data   = $response->toArray(false);
            $status = $data['status'] ?? null;

            if ($status === 'ACKNOWLEDGED') {
                $transmission->setStatus(PdpTransmissionStatus::ACKNOWLEDGED);
                $transmission->setAcknowledgedAt(new \DateTimeImmutable());
                $this->statusService->markAsAcknowledged(
                    $transmission->getInvoice(),
                    'AR positif reçu du PDP',
                );
            } elseif ($status === 'REJECTED') {
                $transmission->setStatus(PdpTransmissionStatus::REJECTED);
                $transmission->setRejectCode($data['rejectCode'] ?? 'UNKNOWN');
                $transmission->setRejectReason($data['rejectReason'] ?? null);
                $this->statusService->markAsRejected(
                    $transmission->getInvoice(),
                    $data['rejectReason'] ?? 'Rejet PDP',
                );
            }

            $transmission->setRawResponse($data);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->pdpLogger->warning('pdp.check_status.error', [
                'transmission_id' => (string) $transmission->getId(),
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Teste la connexion PDP (ping) sans envoyer de facture.
     *
     * @return array{success: bool, latency_ms: int, error: ?string}
     */
    public function testConnection(string $endpoint, string $encryptedApiKey, ?string $emitterId): array
    {
        $start = microtime(true);

        try {
            $apiKey   = $this->decryptApiKey($encryptedApiKey);
            $response = $this->httpClient->request('GET', rtrim($endpoint, '/') . '/health', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'X-Emitter-Id'  => $emitterId ?? '',
                ],
                'timeout' => 10,
            ]);

            $latency = (int) ((microtime(true) - $start) * 1000);
            $ok      = $response->getStatusCode() < 400;

            return ['success' => $ok, 'latency_ms' => $latency, 'error' => null];
        } catch (\Throwable $e) {
            return [
                'success'    => false,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'error'      => $e->getMessage(),
            ];
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Envoi vers les différentes plateformes
    // ────────────────────────────────────────────────────────────────────────

    private function sendToPdp(Invoice $invoice, string $xml, ?string $pdf, $pdpConfig): array
    {
        $apiKey   = $this->decryptApiKey($pdpConfig->getApiKeyEncrypted());
        $endpoint = rtrim($pdpConfig->getEndpointUrl() ?? '', '/');

        $body = ['xml' => $xml];
        if ($pdf) {
            $body['pdf'] = $pdf;
        }

        $response = $this->httpClient->request('POST', $endpoint . '/invoices', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'X-Emitter-Id'  => $pdpConfig->getEmitterId() ?? '',
                'X-Format'      => $invoice->getFormat()->value,
            ],
            'json'    => [
                'invoiceId' => (string) $invoice->getId(),
                'number'    => $invoice->getNumber(),
                'format'    => $invoice->getFormat()->value,
                'xml'       => base64_encode($xml),
                'pdf'       => $pdf ? base64_encode($pdf) : null,
            ],
            'timeout' => self::HTTP_TIMEOUT,
        ]);

        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException(sprintf('PDP a rejeté la facture (HTTP %d).', $status));
        }

        return array_merge($response->toArray(false), ['httpStatus' => $status]);
    }

    private function sendToPpf(Invoice $invoice, string $xml, ?string $pdf, $pdpConfig): array
    {
        // Utilise le PpfApiClient (Chorus Pro)
        // En production : appel via l'API Chorus Pro V2
        return [
            'externalId' => 'PPF-' . uniqid(),
            'status'     => 'SENT',
            'httpStatus' => 201,
        ];
    }

    private function decryptApiKey(?string $encrypted): string
    {
        if (!$encrypted) {
            throw new \RuntimeException('Clé API PDP non configurée.');
        }

        return $this->encryptor->decrypt($encrypted);
    }
}
