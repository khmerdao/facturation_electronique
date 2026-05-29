<?php

declare(strict_types=1);

namespace App\Service\PDP;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP pour l'API Sirene INSEE.
 * Permet de vérifier l'existence et le statut d'un SIRET/SIREN.
 *
 * Documentation : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3.11
 */
final class SireneApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Vérifie qu'un SIRET existe et retourne les informations de l'établissement.
     * Retourne null si le SIRET n'existe pas ou si l'API est indisponible.
     */
    public function findBySiret(string $siret): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/siret/' . $siret, [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            return $data['etablissement'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Vérifie qu'un SIREN existe.
     */
    public function findBySiren(string $siren): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/siren/' . $siren, [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray()['uniteLegale'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retourne true si le SIRET existe et que l'établissement est actif (etatAdministratifEtablissement = 'A').
     */
    public function isActive(string $siret): bool
    {
        $data = $this->findBySiret($siret);

        return $data !== null
            && ($data['periodesEtablissement'][0]['etatAdministratifEtablissement'] ?? null) === 'A';
    }
}
