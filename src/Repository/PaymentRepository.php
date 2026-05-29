<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\PaymentDirection;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\ReceivedInvoice;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Retourne les paiements d'un tenant triés par date décroissante.
     * Utilisé sur /payments avec filtres optionnels.
     */
    public function findByTenant(
        Tenant $tenant,
        ?PaymentDirection $direction = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($direction) {
            $qb->andWhere('p.direction = :dir')->setParameter('dir', $direction);
        }

        if ($from) {
            $qb->andWhere('p.date >= :from')->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('p.date <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les paiements d'une facture émise (encaissements).
     * Utilisé pour calculer le montant restant dû et afficher l'historique.
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les paiements d'une facture reçue (décaissements).
     */
    public function findByReceivedInvoice(ReceivedInvoice $invoice): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.receivedInvoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les paiements à déclarer en e-reporting (non encore reportés).
     * Concerne les prestations de services (TVA sur encaissement).
     * Traité par EReportingPaymentAggregatorJob chaque début de mois.
     *
     * @return Payment[]
     */
    public function findPendingEreporting(Tenant $tenant, string $period): array
    {
        [$year, $month] = explode('-', $period);
        $from = new \DateTimeImmutable("{$year}-{$month}-01");
        $to = $from->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.ereportingRequired = TRUE')
            ->andWhere('p.ereportingReported = FALSE')
            ->andWhere('p.date BETWEEN :from AND :to')
            ->setParameter('tenant', $tenant)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un paiement avec cette clé d'idempotence existe déjà.
     * Évite les doublons lors d'un import bancaire ou d'une création concurrente.
     */
    public function findByIdempotencyKey(string $key): ?Payment
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    /**
     * Calcule le total des encaissements sur une période.
     * Utilisé pour le KPI "Encaissements du mois" sur le dashboard.
     */
    public function sumIncoming(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        return (string) ($this->createQueryBuilder('p')
            ->select('SUM(p.amountEur)')
            ->where('p.tenant = :tenant')
            ->andWhere('p.direction = :dir')
            ->andWhere('p.date BETWEEN :from AND :to')
            ->setParameter('tenant', $tenant)
            ->setParameter('dir', PaymentDirection::INCOMING)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult() ?? '0');
    }
}
