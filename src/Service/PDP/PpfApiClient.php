<?php

declare(strict_types=1);

namespace App\Service\PDP;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP pour le PPF (Portail Public de Facturation — Chorus Pro).
 * Utilisé pour les entreprises qui n'ont pas de PDP partenaire et
 * soumettent leurs factures directement au PPF.
 *
 * Documentation Chorus Pro : https://api.chorus-pro.gouv.fr
 */
final class PpfApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Soumet une facture au PPF au format Factur-X / UBL / CII.
     *
     * @param string $invoiceXml  Contenu XML de la facture
     * @param string $format      'FACTURX' | 'UBL' | 'CII'
     * @return array{success: bool, externalId: ?string, error: ?string}
     */
    public function submitInvoice(string $invoiceXml, string $format = 'FACTURX'): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/invoices', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/xml',
                    'X-Format'      => $format,
                ],
                'body' => $invoiceXml,
            ]);

            $status = $response->getStatusCode();

            if ($status === 201 || $status === 200) {
                $data = $response->toArray(false);

                return [
                    'success'    => true,
                    'externalId' => $data['id'] ?? null,
                    'error'      => null,
                ];
            }

            return [
                'success'    => false,
                'externalId' => null,
                'error'      => 'HTTP ' . $status,
            ];
        } catch (\Throwable $e) {
            return [
                'success'    => false,
                'externalId' => null,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Retourne le statut courant d'une facture soumise au PPF.
     */
    public function getInvoiceStatus(string $externalId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/invoices/' . $externalId, [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray(false)['status'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Teste la connectivité avec le PPF (ping).
     */
    public function ping(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/health', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
