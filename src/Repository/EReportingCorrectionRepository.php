<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EReportingBatch;
use App\Entity\EReportingCorrection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EReportingCorrection>
 */
class EReportingCorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EReportingCorrection::class);
    }

    /**
     * Retourne les corrections d'un batch triées par date.
     * Affichées dans la section "Corrections" de /e-reporting/{id}.
     */
    public function findByBatch(EReportingBatch $batch): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.batch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
