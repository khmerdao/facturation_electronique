<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EReportingBatch;
use App\Entity\EReportingTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EReportingTransaction>
 */
class EReportingTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EReportingTransaction::class);
    }

    /**
     * Retourne les transactions d'un batch, regroupées par type.
     * Utilisé pour la génération du XML DGFiP et l'affichage du détail.
     */
    public function findByBatch(EReportingBatch $batch): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.batch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('t.type', 'ASC')
            ->addOrderBy('t.transactionDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les totaux agrégés d'un batch (pour le résumé avant soumission).
     *
     * @return array{totalHt: string, totalTva: string, transactionCount: int}
     */
    public function getTotals(EReportingBatch $batch): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'SUM(t.totalHt) AS totalHt',
                'SUM(t.totalTva) AS totalTva',
                'SUM(t.transactionCount) AS transactionCount',
            )
            ->where('t.batch = :batch')
            ->setParameter('batch', $batch)
            ->getQuery()
            ->getSingleResult();
    }
}
