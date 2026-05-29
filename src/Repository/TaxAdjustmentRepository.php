<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TaxAdjustment;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxAdjustment>
 */
class TaxAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxAdjustment::class);
    }

    /**
     * Retourne les ajustements de TVA d'un tenant sur une période.
     * Utilisé sur /tax pour compléter les montants des déclarations.
     */
    public function findByPeriod(Tenant $tenant, string $period): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.tenant = :tenant')
            ->andWhere('a.period = :period')
            ->setParameter('tenant', $tenant)
            ->setParameter('period', $period)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total des ajustements TVA collectée par période.
     * Additionné aux montants des factures pour l'aide CA3.
     */
    public function sumCollectedByPeriod(Tenant $tenant, string $period): string
    {
        return (string) ($this->createQueryBuilder('a')
            ->select('SUM(a.amount)')
            ->where('a.tenant = :tenant')
            ->andWhere('a.period = :period')
            ->andWhere('a.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('period', $period)
            ->setParameter('type', 'collected')
            ->getQuery()
            ->getSingleScalarResult() ?? '0');
    }

    /**
     * Calcule le total des ajustements TVA déductible par période.
     */
    public function sumDeductibleByPeriod(Tenant $tenant, string $period): string
    {
        return (string) ($this->createQueryBuilder('a')
            ->select('SUM(a.amount)')
            ->where('a.tenant = :tenant')
            ->andWhere('a.period = :period')
            ->andWhere('a.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('period', $period)
            ->setParameter('type', 'deductible')
            ->getQuery()
            ->getSingleScalarResult() ?? '0');
    }
}
