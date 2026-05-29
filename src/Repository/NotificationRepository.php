<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\NotificationSeverity;
use App\Entity\Notification;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Retourne les notifications d'un utilisateur (personnelles + équipe),
     * non ignorées, triées par date décroissante.
     * "Équipe" = notifications dont user IS NULL (visibles par toute l'équipe).
     *
     * Utilisé sur /notifications pour la liste principale.
     */
    public function findForUser(
        User $user,
        Tenant $tenant,
        bool $unreadOnly = false,
        ?string $type = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->where('n.tenant = :tenant')
            ->andWhere('n.dismissedAt IS NULL')
            ->andWhere('n.user = :user OR n.user IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        if ($type) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     * Utilisé pour le badge dans la navbar (mis à jour via Turbo Stream).
     */
    public function countUnread(User $user, Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.tenant = :tenant')
            ->andWhere('n.user = :user OR n.user IS NULL')
            ->andWhere('n.readAt IS NULL')
            ->andWhere('n.dismissedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque toutes les notifications non lues d'un utilisateur comme lues.
     * Appelé par le bouton "Tout marquer comme lu" sur /notifications.
     */
    public function markAllAsRead(User $user, Tenant $tenant): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.tenant = :tenant')
            ->andWhere('n.user = :user OR n.user IS NULL')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('tenant', $tenant)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Retourne les notifications non lues et non ignorées de sévérité DANGER.
     * Affichées en toast sur toutes les pages si l'utilisateur est connecté.
     */
    public function findUndismissedDangers(User $user, Tenant $tenant): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.tenant = :tenant')
            ->andWhere('n.user = :user OR n.user IS NULL')
            ->andWhere('n.severity = :sev')
            ->andWhere('n.dismissedAt IS NULL')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('user', $user)
            ->setParameter('sev', NotificationSeverity::DANGER)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les notifications destinées à l'envoi en digest (quotidien ou hebdo).
     * Filtrées sur les préférences de l'utilisateur.
     * Utilisé par DigestEmailService (Messenger scheduled).
     *
     * @param string $digest 'DAILY' | 'WEEKLY'
     * @return Notification[]
     */
    public function findForDigest(User $user, Tenant $tenant, string $digest, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.tenant = :tenant')
            ->andWhere('n.user = :user OR n.user IS NULL')
            ->andWhere('n.readAt IS NULL')
            ->andWhere('n.dismissedAt IS NULL')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('tenant', $tenant)
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('n.severity', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
