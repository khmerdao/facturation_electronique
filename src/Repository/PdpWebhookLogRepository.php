<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PdpWebhookLog;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PdpWebhookLog>
 */
class PdpWebhookLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PdpWebhookLog::class);
    }

    /**
     * Vérifie si un événement PDP a déjà été traité (idempotence).
     * Appelé en début du handler de webhook pour éviter le double traitement.
     * Clé unique : (tenant_id, event_id).
     */
    public function existsByEventId(Tenant $tenant, string $eventId): bool
    {
        return (bool) $this->createQueryBuilder('w')
            ->select('1')
            ->where('w.tenant = :tenant')
            ->andWhere('w.eventId = :eventId')
            ->setParameter('tenant', $tenant)
            ->setParameter('eventId', $eventId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les webhooks non traités (processed = false) pour rejeu.
     * Utilisé par un worker de récupération en cas d'erreur de traitement.
     *
     * @return PdpWebhookLog[]
     */
    public function findUnprocessed(int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.processed = FALSE')
            ->andWhere('w.processingError IS NOT NULL')
            ->orderBy('w.receivedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les derniers webhooks reçus d'un tenant (pour le debug).
     * Affiché dans /settings/pdp, onglet "Activité récente".
     */
    public function findRecentByTenant(Tenant $tenant, int $limit = 20): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('w.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
