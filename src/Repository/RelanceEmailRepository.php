<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\RelanceEmail;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RelanceEmail>
 */
class RelanceEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RelanceEmail::class);
    }

    /**
     * Retourne l'historique des relances envoyées pour une facture.
     * Affiché sur /invoices/{id} dans la section "Relances".
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('r.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le niveau de la dernière relance envoyée pour une facture.
     * Permet de déterminer le niveau suivant (1 → 2 → 3).
     * Retourne 0 si aucune relance n'a encore été envoyée.
     */
    public function getLastLevel(Invoice $invoice): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.level)')
            ->where('r.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Retourne les statistiques de relances du tenant (nb par niveau, taux d'ouverture).
     * Utilisé sur /payments pour le résumé des relances en cours.
     */
    public function getStatsByTenant(Tenant $tenant, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.level AS level, COUNT(r.id) AS cnt, SUM(CASE WHEN r.openedAt IS NOT NULL THEN 1 ELSE 0 END) AS opened')
            ->where('r.tenant = :tenant')
            ->andWhere('r.sentAt >= :since')
            ->setParameter('tenant', $tenant)
            ->setParameter('since', $since)
            ->groupBy('r.level')
            ->orderBy('r.level', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
