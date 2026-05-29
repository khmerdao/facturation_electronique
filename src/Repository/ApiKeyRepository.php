<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiKey;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    /**
     * Trouve une clé d'API à partir de son hash SHA-256.
     * Point d'entrée unique de l'authentification API (ApiKeyAuthenticator).
     * La clé en clair n'est jamais stockée — seul le hash est comparé.
     */
    public function findByHash(string $keyHash): ?ApiKey
    {
        return $this->createQueryBuilder('k')
            ->where('k.keyHash = :hash')
            ->andWhere('k.revokedAt IS NULL')
            ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
            ->setParameter('hash', $keyHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne toutes les clés actives d'un tenant.
     * Utilisé sur /settings/integrations pour afficher les clés (préfixe seulement).
     */
    public function findActiveByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('k')
            ->where('k.tenant = :tenant')
            ->andWhere('k.revokedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la date de dernière utilisation sans déclencher d'événements.
     * Appelé à chaque requête API authentifiée (performance : UPDATE direct).
     */
    public function touchLastUsed(ApiKey $apiKey): void
    {
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.lastUsedAt', ':now')
            ->where('k.id = :id')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $apiKey->getId())
            ->getQuery()
            ->execute();
    }
}
