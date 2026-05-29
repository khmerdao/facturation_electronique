<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\ExportStatus;
use App\Entity\Enum\ExportType;
use App\Entity\ExportJob;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExportJob>
 */
class ExportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExportJob::class);
    }

    /**
     * Retourne les exports récents d'un tenant (non expirés).
     * Utilisé sur /exports pour lister les fichiers téléchargeables.
     */
    public function findRecentByTenant(Tenant $tenant, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.tenant = :tenant')
            ->andWhere('e.expiresAt IS NULL OR e.expiresAt > :now')
            ->setParameter('tenant', $tenant)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les exports en attente de traitement par le worker.
     * Dépilés par ExportWorkerHandler (Messenger consumer).
     *
     * @return ExportJob[]
     */
    public function findPending(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', ExportStatus::PENDING)
            ->orderBy('e.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Purge les exports expirés (fichier S3 déjà supprimé ou expiré).
     * Appelé par un job de maintenance quotidien.
     */
    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->where('e.expiresAt < :now')
            ->andWhere('e.status = :done')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('done', ExportStatus::DONE)
            ->getQuery()
            ->execute();
    }
}
