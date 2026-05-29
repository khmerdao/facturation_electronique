<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EReportingBatch;
use App\Entity\EReportingPaymentLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EReportingPaymentLine>
 */
class EReportingPaymentLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EReportingPaymentLine::class);
    }

    /**
     * Retourne les lignes de paiement d'un batch triées par date.
     * Utilisé pour la génération du fichier XML des données de paiement DGFiP.
     */
    public function findByBatch(EReportingBatch $batch): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.batch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('p.paymentDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
