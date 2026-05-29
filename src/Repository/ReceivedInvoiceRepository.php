<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\ReceivedInvoiceStatus;
use App\Entity\ReceivedInvoice;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReceivedInvoice>
 */
class ReceivedInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReceivedInvoice::class);
    }

    /**
     * Retourne les factures reçues avec filtres et pagination.
     * Utilisé sur /received-invoices.
     *
     * @param array{
     *   status?: ReceivedInvoiceStatus[],
     *   supplierId?: string,
     *   parseError?: bool,
     *   from?: \DateTimeImmutable,
     *   to?: \DateTimeImmutable,
     * } $filters
     * @return ReceivedInvoice[]
     */
    public function findByFilters(
        Tenant $tenant,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->where('r.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('r.receivedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (!empty($filters['status'])) {
            $qb->andWhere('r.status IN (:statuses)')
                ->setParameter('statuses', array_map(
                    fn (ReceivedInvoiceStatus $s) => $s->value,
                    $filters['status'],
                ));
        }

        if (!empty($filters['supplierId'])) {
            $qb->andWhere('CAST(r.supplierContact AS STRING) = :supplierId')
                ->setParameter('supplierId', $filters['supplierId']);
        }

        if (!empty($filters['parseError'])) {
            $qb->andWhere('r.status = :errStatus')
                ->setParameter('errStatus', ReceivedInvoiceStatus::PARSE_ERROR->value);
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('r.receivedAt >= :from')->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $qb->andWhere('r.receivedAt <= :to')->setParameter('to', $filters['to']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une facture reçue existe déjà pour cet identifiant PDP.
     * Garantit l'idempotence du traitement des webhooks PDP.
     * Clé : (tenant_id, external_pdp_id) — voir UniqueConstraint.
     */
    public function existsByExternalPdpId(Tenant $tenant, string $externalPdpId): bool
    {
        return (bool) $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.tenant = :tenant')
            ->andWhere('r.externalPdpId = :extId')
            ->setParameter('tenant', $tenant)
            ->setParameter('extId', $externalPdpId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne les factures reçues dont l'accusé de réception technique
     * n'a pas encore été envoyé. Obligatoire depuis le 1er septembre 2026.
     * Traité par un worker toutes les 5 minutes.
     *
     * @return ReceivedInvoice[]
     */
    public function findPendingTechnicalAck(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.technicalAckSentAt IS NULL')
            ->andWhere('r.status != :errStatus')
            ->setParameter('errStatus', ReceivedInvoiceStatus::PARSE_ERROR->value)
            ->setMaxResults($limit)
            ->orderBy('r.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les factures en erreur de parsing, toutes tenants confondues.
     * Utilisé par un job de monitoring pour alerter le support.
     *
     * @return ReceivedInvoice[]
     */
    public function findParseErrors(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', ReceivedInvoiceStatus::PARSE_ERROR)
            ->orderBy('r.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les KPIs des factures reçues pour le dashboard.
     *
     * @return array{countPending: int, countApproved: int, totalToPay: string}
     */
    public function getKpis(Tenant $tenant): array
    {
        return $this->createQueryBuilder('r')
            ->select(
                'SUM(CASE WHEN r.status = \'PENDING_VALIDATION\' THEN 1 ELSE 0 END) AS countPending',
                'SUM(CASE WHEN r.status = \'APPROVED\' THEN 1 ELSE 0 END) AS countApproved',
                'SUM(CASE WHEN r.status = \'APPROVED\' THEN r.amountTtc - r.amountPaid ELSE 0 END) AS totalToPay',
            )
            ->where('r.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleResult();
    }
}
