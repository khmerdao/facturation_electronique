<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\Plan;
use App\Entity\Enum\TenantStatus;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    /**
     * Trouve un tenant par son slug (identifiant URL).
     * Utilisé lors de la sélection du tenant actif après login.
     */
    public function findBySlug(string $slug): ?Tenant
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Trouve un tenant par son SIRET. Utilisé à l'inscription pour détecter
     * un doublon ou pré-remplir les données depuis Sirene.
     */
    public function findBySiret(string $siret): ?Tenant
    {
        return $this->findOneBy(['siret' => $siret]);
    }

    /**
     * Retourne tous les tenants actifs (pour le super-admin).
     * Exclut les tenants supprimés (soft delete).
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status != :deleted')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('deleted', TenantStatus::DELETED)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les tenants avec leurs statistiques agrégées.
     * Utilisé dans /admin/tenants pour le tableau de bord super-admin.
     * Jointure avec invoices et memberships pour les compteurs.
     */
    public function findAllWithStats(): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't',
                'COUNT(DISTINCT m.id) AS memberCount',
                'COUNT(DISTINCT i.id) AS invoiceCount',
            )
            ->leftJoin('t.memberships', 'm', 'WITH', 'm.joinedAt IS NOT NULL')
            ->leftJoin('App\Entity\Invoice', 'i', 'WITH', 'i.tenant = t AND i.createdAt >= :monthStart')
            ->where('t.deletedAt IS NULL')
            ->groupBy('t.id')
            ->setParameter('monthStart', new \DateTimeImmutable('first day of this month midnight'))
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les tenants dont l'onboarding est incomplet depuis plus de X jours.
     * Utilisé pour les alertes super-admin "onboarding bloqué".
     */
    public function findStuckOnboarding(int $daysThreshold = 7): array
    {
        $limit = new \DateTimeImmutable("-{$daysThreshold} days");

        return $this->createQueryBuilder('t')
            ->where('t.onboardingCompleted = FALSE')
            ->andWhere('t.createdAt < :limit')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('limit', $limit)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le nombre de tenants par plan. Utilisé pour les KPIs globaux
     * du super-admin (/admin/tenants).
     *
     * @return array<array{plan: string, count: int}>
     */
    public function countByPlan(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.plan AS plan, COUNT(t.id) AS count')
            ->where('t.deletedAt IS NULL')
            ->groupBy('t.plan')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Retourne les tenants inscrits dans la période donnée.
     * Utilisé pour le graphique "nouveaux tenants" sur /admin/tenants.
     */
    public function findCreatedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche full-text sur nom, SIRET, email de facturation.
     * Utilisé dans la barre de recherche /admin/tenants.
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name) LIKE :q')
            ->orWhere('t.siret LIKE :q')
            ->orWhere('LOWER(t.billingEmail) LIKE :q')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setMaxResults($limit)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les tenants proches de la limite de leur plan (>= $threshold %).
     * Utilisé pour envoyer les notifications PLAN_LIMIT_APPROACHING.
     */
    public function findApproachingLimits(int $threshold = 80): array
    {
        // La logique de comparaison avec la limite dépend du plan :
        // elle est calculée applicativement après récupération
        return $this->createQueryBuilder('t')
            ->where('t.status = :active')
            ->andWhere('t.plan != :enterprise')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('active', TenantStatus::ACTIVE)
            ->setParameter('enterprise', Plan::ENTERPRISE)
            ->getQuery()
            ->getResult();
    }
}
