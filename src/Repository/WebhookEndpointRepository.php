<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant;
use App\Entity\WebhookEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookEndpoint>
 */
class WebhookEndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEndpoint::class);
    }

    /**
     * Retourne les endpoints actifs d'un tenant souscrits à un événement donné.
     * Appelé par WebhookDispatcher pour chaque événement à livrer.
     * Ex : findActiveForEvent($tenant, 'invoice.paid')
     */
    public function findActiveForEvent(Tenant $tenant, string $event): array
    {
        // JSON_CONTAINS n'est pas standard en DQL — utilisation d'une requête native
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
            SELECT we.id FROM webhook_endpoints we
            WHERE we.tenant_id = :tenantId
              AND we.active = TRUE
              AND (we.events @> :eventJson OR we.events @> '["*"]')
        SQL;

        $ids = $conn->fetchFirstColumn($sql, [
            'tenantId' => (string) $tenant->getId(),
            'eventJson' => json_encode([$event]),
        ]);

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les endpoints d'un tenant (actifs et inactifs).
     * Utilisé sur /settings/integrations.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les endpoints avec trop d'échecs consécutifs (auto-disable).
     * Un endpoint est désactivé après 10 échecs (threshold configurable).
     * Utilisé par WebhookDeactivationJob.
     */
    public function findToDeactivate(int $failureThreshold = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.active = TRUE')
            ->andWhere('e.failureCount >= :threshold')
            ->setParameter('threshold', $failureThreshold)
            ->getQuery()
            ->getResult();
    }
}
