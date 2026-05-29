<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EReportingBatch;
use App\Entity\Enum\EReportingStatus;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EReportingBatch>
 */
class EReportingBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EReportingBatch::class);
    }

    /**
     * Retourne le batch d'une période donnée pour un tenant.
     * Clé unique (tenant_id, period). Retourne null si le batch n'existe pas encore.
     * Utilisé au début de chaque mois par CreateMonthlyEReportingBatchJob.
     */
    public function findByPeriod(Tenant $tenant, string $period): ?EReportingBatch
    {
        return $this->createQueryBuilder('b')
            ->where('b.tenant = :tenant')
            ->andWhere('b.period = :period')
            ->setParameter('tenant', $tenant)
            ->setParameter('period', $period)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les batches d'un tenant, du plus récent au plus ancien.
     * Utilisé sur /e-reporting pour la liste des transmissions DGFiP.
     */
    public function findByTenant(Tenant $tenant, int $limit = 24): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('b.period', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les batches en retard (deadline dépassée, non soumis).
     * Utilisé pour les notifications EREPORTING_BATCH_LATE et l'alerte
     * super-admin (/admin/logs, onglet "E-reporting DGFiP").
     */
    public function findLate(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status NOT IN (:doneStatuses)')
            ->andWhere('b.deadline < :today')
            ->setParameter('doneStatuses', [
                EReportingStatus::SUBMITTED->value,
                EReportingStatus::ACCEPTED->value,
                EReportingStatus::EMPTY->value,
            ])
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les batches à soumettre prochainement (dans les X jours).
     * Déclenche la notification EREPORTING_BATCH_DUE.
     */
    public function findDueSoon(int $daysAhead = 5): array
    {
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify("+{$daysAhead} days");

        return $this->createQueryBuilder('b')
            ->where('b.status IN (:pendingStatuses)')
            ->andWhere('b.deadline BETWEEN :today AND :limit')
            ->setParameter('pendingStatuses', [
                EReportingStatus::NOT_STARTED->value,
                EReportingStatus::DRAFT->value,
                EReportingStatus::READY->value,
            ])
            ->setParameter('today', $today)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les batches de toutes tenants (super-admin uniquement).
     * Nécessite que le TenantFilter soit désactivé.
     */
    public function findAllTenants(int $page = 1, int $perPage = 50): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.period', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }
}
