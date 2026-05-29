<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\Role;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantMembership>
 */
class TenantMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantMembership::class);
    }

    /**
     * Retourne le membership d'un utilisateur dans un tenant donné.
     * Retourne null si l'utilisateur n'appartient pas à ce tenant.
     * Utilisé pour le contrôle d'accès et l'injection du rôle dans le token.
     */
    public function findOneByUserAndTenant(User $user, Tenant $tenant): ?TenantMembership
    {
        return $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->andWhere('m.tenant = :tenant')
            ->setParameter('user', $user)
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les tenants auxquels appartient un utilisateur.
     * Utilisé après le login pour afficher le sélecteur de tenant
     * quand l'utilisateur est membre de plusieurs organisations.
     */
    public function findTenantsForUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.tenant', 't')
            ->where('m.user = :user')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les membres actifs d'un tenant avec leurs détails.
     * "Actif" = joinedAt IS NOT NULL (invitation acceptée).
     * Utilisé sur /settings/users.
     */
    public function findActiveMemberships(Tenant $tenant): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.user', 'u')
            ->where('m.tenant = :tenant')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->orderBy('m.role', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de membres actifs (hors invitations en attente).
     * Utilisé pour vérifier la limite du plan avant d'inviter un nouveau membre.
     */
    public function countActiveMembers(Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.tenant = :tenant')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie qu'un tenant a au moins un membre avec le rôle OWNER.
     * Appelé avant la révocation d'un OWNER pour éviter un tenant orphelin.
     */
    public function countOwners(Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.tenant = :tenant')
            ->andWhere('m.role = :role')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('role', Role::OWNER)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
