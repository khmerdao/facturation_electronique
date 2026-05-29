<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant;
use App\Entity\TenantInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantInvitation>
 */
class TenantInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantInvitation::class);
    }

    /**
     * Trouve une invitation valide (non expirée, non acceptée) par son token.
     * Utilisé sur /register pour pré-remplir l'inscription via invitation.
     */
    public function findValidByToken(string $token): ?TenantInvitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.token = :token')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les invitations en attente d'un tenant (non acceptées, non expirées).
     * Utilisé dans /settings/users pour lister les invitations pendantes.
     */
    public function findPendingByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('tenant', $tenant)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie qu'une invitation active existe déjà pour cet email dans ce tenant.
     * Empêche l'envoi de doublons lors de l'invitation d'un collaborateur.
     */
    public function hasPendingInvitation(Tenant $tenant, string $email): bool
    {
        return (bool) $this->createQueryBuilder('i')
            ->select('1')
            ->where('i.tenant = :tenant')
            ->andWhere('LOWER(i.email) = LOWER(:email)')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('tenant', $tenant)
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
