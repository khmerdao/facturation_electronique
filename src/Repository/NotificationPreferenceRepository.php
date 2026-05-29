<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    /**
     * Retourne toutes les préférences de notification d'un utilisateur,
     * indexées par type. L'absence d'un type = valeurs par défaut (in-app ON,
     * email ON, digest IMMEDIATE).
     *
     * @return array<string, NotificationPreference>
     */
    public function findIndexedByType(User $user): array
    {
        $prefs = $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($prefs as $pref) {
            $indexed[$pref->getNotificationType()] = $pref;
        }

        return $indexed;
    }

    /**
     * Retourne les utilisateurs ayant activé l'email digest d'un type donné.
     * Utilisé par DigestEmailService pour cibler les destinataires.
     *
     * @param string $digest 'DAILY' | 'WEEKLY'
     * @return User[]
     */
    public function findUsersWithDigest(string $notificationType, string $digest): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT u')
            ->join('p.user', 'u')
            ->where('p.notificationType = :type')
            ->andWhere('p.emailEnabled = TRUE')
            ->andWhere('p.emailDigest = :digest')
            ->setParameter('type', $notificationType)
            ->setParameter('digest', $digest)
            ->getQuery()
            ->getResult();
    }
}
