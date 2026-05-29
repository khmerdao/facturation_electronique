<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Retourne les entrées d'audit d'un tenant avec filtres.
     * Utilisé sur /admin/logs onglet "Audit global" et dans l'export
     * d'audit (obligation légale : conservation 10 ans).
     *
     * @param array{
     *   userId?: string,
     *   action?: string,
     *   entityType?: string,
     *   entityId?: string,
     *   from?: \DateTimeImmutable,
     *   to?: \DateTimeImmutable,
     *   impersonatedOnly?: bool,
     * } $filters
     * @return AuditLog[]
     */
    public function findByTenant(
        Tenant $tenant,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (!empty($filters['userId'])) {
            $qb->andWhere('CAST(a.user AS STRING) = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        if (!empty($filters['entityId'])) {
            $qb->andWhere('a.entityId = :entityId')
                ->setParameter('entityId', $filters['entityId']);
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $filters['to']);
        }

        if (!empty($filters['impersonatedOnly'])) {
            $qb->andWhere('a.isImpersonated = TRUE');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne tous les logs d'audit, toutes tenants confondues.
     * Nécessite que le TenantFilter Doctrine soit désactivé.
     * Uniquement accessible aux super-admins (/admin/logs).
     *
     * @return AuditLog[]
     */
    public function findAllTenants(
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (!empty($filters['tenantId'])) {
            $qb->andWhere('CAST(a.tenant AS STRING) = :tid')
                ->setParameter('tid', $filters['tenantId']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne l'historique d'audit d'une entité spécifique (toutes actions).
     * Ex : toutes les modifications d'une facture Invoice#uuid.
     * Utilisé pour la piste d'audit fiable d'un document.
     *
     * @return AuditLog[]
     */
    public function findByEntity(Tenant $tenant, string $entityType, string $entityId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.tenant = :tenant')
            ->andWhere('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('tenant', $tenant)
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
