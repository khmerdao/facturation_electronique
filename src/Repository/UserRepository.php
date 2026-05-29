<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Met à jour le hash du mot de passe (rehashing automatique Symfony).
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Expected %s, got %s.', User::class, $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un utilisateur par son email (case-insensitive).
     * Utilisé par AppAuthenticator et le formulaire de login.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les membres actifs d'un tenant, avec leur rôle.
     * Utilisé sur /settings/users et dans le sélecteur d'acteur.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.memberships', 'm')
            ->where('m.tenant = :tenant')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un email est déjà utilisé dans la plateforme.
     * Utilisé à l'inscription et lors de l'invitation d'un membre.
     */
    public function emailExists(string $email): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('1')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Met à jour la date de dernière connexion sans déclencher les listeners
     * (performance : pas d'audit log sur cette action).
     */
    public function updateLastLogin(User $user): void
    {
        $this->createQueryBuilder('u')
            ->update()
            ->set('u.lastLoginAt', ':now')
            ->where('u.id = :id')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $user->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * Retourne les utilisateurs inactifs (jamais connectés depuis X jours).
     * Utilisé dans /settings/users pour détecter les comptes abandonnés.
     */
    public function findInactiveSince(\DateTimeImmutable $since, Tenant $tenant): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.memberships', 'm')
            ->where('m.tenant = :tenant')
            ->andWhere('m.joinedAt IS NOT NULL')
            ->andWhere('u.lastLoginAt < :since OR u.lastLoginAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }
}
