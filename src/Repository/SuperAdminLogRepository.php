<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SuperAdminLog;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuperAdminLog>
 *
 * N'est jamais filtré par le TenantFilter (SuperAdminLog ne porte pas
 * TenantAwareTrait). Réservé exclusivement aux super-admins.
 */
class SuperAdminLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuperAdminLog::class);
    }

    /**
     * Retourne tous les logs super-admin, du plus récent au plus ancien.
     * Affiché dans /admin/logs onglet "Actions super-admin".
     */
    public function findAll(
        ?string $action = null,
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($action) {
            $qb->where('l.action LIKE :action')
                ->setParameter('action', '%' . $action . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les logs d'actions sur un tenant spécifique.
     * Affiché dans /admin/tenants/{id} onglet "Historique super-admin".
     */
    public function findByTargetTenant(Tenant $tenant, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.targetTenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les logs d'un super-admin spécifique.
     * Utile pour l'audit des actions d'un opérateur de la plateforme.
     */
    public function findBySuperAdmin(User $superAdmin, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.superAdmin = :admin')
            ->setParameter('admin', $superAdmin)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne toutes les sessions d'impersonation sur un tenant.
     * Permet d'auditer qui a accédé à quel tenant et combien de temps.
     *
     * @return SuperAdminLog[] Paires start/end triées par date
     */
    public function findImpersonationSessions(Tenant $tenant): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.targetTenant = :tenant')
            ->andWhere('l.action IN (:actions)')
            ->setParameter('tenant', $tenant)
            ->setParameter('actions', ['impersonation.started', 'impersonation.ended'])
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
