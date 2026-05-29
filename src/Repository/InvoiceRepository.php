<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Invoice;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Retourne les factures d'un tenant avec filtres, pagination et tri.
     * Méthode principale de /invoices (liste). Tous les paramètres sont
     * optionnels pour permettre un filtrage progressif côté UI.
     *
     * @param array{
     *   status?: InvoiceStatus[],
     *   type?: InvoiceType,
     *   contactId?: string,
     *   search?: string,
     *   from?: \DateTimeImmutable,
     *   to?: \DateTimeImmutable,
     *   overdue?: bool,
     * } $filters
     * @return Invoice[]
     */
    public function findByFilters(
        Tenant $tenant,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortField = 'issueDate',
        string $sortDir = 'DESC',
    ): array {
        $qb = $this->baseQuery($tenant)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->orderBy("i.{$sortField}", $sortDir);

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les factures correspondant aux filtres.
     * Utilisé avec findByFilters pour la pagination.
     */
    public function countByFilters(Tenant $tenant, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.tenant = :tenant')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('tenant', $tenant);

        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retourne les KPIs du dashboard (CA, factures en attente, impayées…).
     * Calcul en une seule requête agrégée pour la performance.
     *
     * @return array{
     *   totalSent: float,
     *   totalAcknowledged: float,
     *   totalPaid: float,
     *   countDraft: int,
     *   countOverdue: int,
     *   avgDaysToPayment: float,
     * }
     */
    public function getKpis(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('i')
            ->select(
                'SUM(CASE WHEN i.status IN (\'SENT\',\'ACKNOWLEDGED\',\'PAID\') THEN i.totalTtc ELSE 0 END) AS totalSent',
                'SUM(CASE WHEN i.status = \'ACKNOWLEDGED\' THEN i.totalTtc ELSE 0 END) AS totalAcknowledged',
                'SUM(CASE WHEN i.status = \'PAID\' THEN i.totalTtc ELSE 0 END) AS totalPaid',
                'SUM(CASE WHEN i.status = \'DRAFT\' THEN 1 ELSE 0 END) AS countDraft',
                'SUM(CASE WHEN i.status = \'ACKNOWLEDGED\' AND i.dueDate < :today THEN 1 ELSE 0 END) AS countOverdue',
            )
            ->where('i.tenant = :tenant')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('i.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('type', InvoiceType::INVOICE)
            ->getQuery()
            ->getSingleResult();

        return $result;
    }

    /**
     * Retourne les factures émises en retard (ACKNOWLEDGED, dueDate dépassée).
     * Utilisé pour les alertes dashboard et le déclenchement des relances.
     *
     * @return Invoice[]
     */
    public function findOverdue(Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueDate < :today')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', InvoiceStatus::ACKNOWLEDGED)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les factures dont l'échéance approche (dans les X prochains jours).
     * Utilisé pour la notification INVOICE_DUE_SOON.
     *
     * @return Invoice[]
     */
    public function findDueSoon(Tenant $tenant, int $daysAhead = 7): array
    {
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify("+{$daysAhead} days");

        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueDate BETWEEN :today AND :limit')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', InvoiceStatus::ACKNOWLEDGED)
            ->setParameter('today', $today)
            ->setParameter('limit', $limit)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne toutes les factures d'un contact (historique client).
     * Utilisé sur la fiche contact /contacts/{id}, onglet "Factures".
     *
     * @return Invoice[]
     */
    public function findByContact(Contact $contact, Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.contact = :contact')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('contact', $contact)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les factures pour la génération du FEC (Fichier des Écritures Comptables).
     * Filtre sur les factures ACKNOWLEDGED + PAID dans la période, avec leurs lignes.
     * Requis par l'arrêté du 29/07/2013.
     *
     * @return Invoice[]
     */
    public function findForFec(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('l')
            ->leftJoin('i.lines', 'l')
            ->where('i.tenant = :tenant')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('i.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', [InvoiceStatus::ACKNOWLEDGED->value, InvoiceStatus::PAID->value])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('type', InvoiceType::INVOICE)
            ->orderBy('i.issueDate', 'ASC')
            ->addOrderBy('i.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les factures pour l'agrégation e-reporting mensuel.
     * Seules les factures B2C/international (sans pdpIdentifier client)
     * sont concernées par l'e-reporting transaction.
     *
     * @return Invoice[]
     */
    public function findForEreporting(Tenant $tenant, string $period): array
    {
        [$year, $month] = explode('-', $period);
        $from = new \DateTimeImmutable("{$year}-{$month}-01");
        $to = $from->modify('last day of this month');

        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('i.clientPdpIdentifier IS NULL')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('i.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', [InvoiceStatus::ACKNOWLEDGED->value, InvoiceStatus::PAID->value])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('type', InvoiceType::INVOICE)
            ->orderBy('i.issueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le CA mensuel sur les 12 derniers mois (pour le graphique dashboard).
     * Résultat : tableau [['month' => 'YYYY-MM', 'total' => float], ...]
     */
    public function getMonthlyRevenue(Tenant $tenant, int $months = 12): array
    {
        $from = new \DateTimeImmutable("first day of -{$months} months midnight");

        return $this->createQueryBuilder('i')
            ->select(
                "TO_CHAR(i.issueDate, 'YYYY-MM') AS month",
                'SUM(i.totalTtc) AS total',
            )
            ->where('i.tenant = :tenant')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.issueDate >= :from')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('i.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', [InvoiceStatus::ACKNOWLEDGED->value, InvoiceStatus::PAID->value])
            ->setParameter('from', $from)
            ->setParameter('type', InvoiceType::INVOICE)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Retourne les avoirs liés à une facture d'origine.
     * Utilisé sur la page de détail /invoices/{id} pour afficher les avoirs associés.
     *
     * @return Invoice[]
     */
    public function findCreditNotes(Invoice $original): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.creditNoteFor = :original')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('original', $original)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les statistiques TVA par taux sur une période.
     * Utilisé sur /tax pour l'aide à la déclaration CA3.
     *
     * @return array<array{tvaRate: string, totalHt: string, totalTva: string}>
     */
    public function getTvaStats(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('i')
            ->select(
                'l.tvaRate AS tvaRate',
                'SUM(l.amountHt) AS totalHt',
                'SUM(l.amountTva) AS totalTva',
            )
            ->join('i.lines', 'l')
            ->where('i.tenant = :tenant')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('i.deletedAt IS NULL')
            ->andWhere('l.isComment = FALSE')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', [InvoiceStatus::ACKNOWLEDGED->value, InvoiceStatus::PAID->value])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('l.tvaRate')
            ->orderBy('l.tvaRate', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Requête de base filtrée sur le tenant et les non-supprimées.
     * Factorisée pour findByFilters et countByFilters.
     */
    private function baseQuery(Tenant $tenant): QueryBuilder
    {
        return $this->createQueryBuilder('i')
            ->where('i.tenant = :tenant')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('tenant', $tenant);
    }

    /**
     * Applique les filtres dynamiques sur un QueryBuilder.
     * Factorisée pour findByFilters et countByFilters.
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['status'])) {
            $qb->andWhere('i.status IN (:statuses)')
                ->setParameter('statuses', array_map(
                    fn (InvoiceStatus $s) => $s->value,
                    $filters['status'],
                ));
        }

        if (isset($filters['type'])) {
            $qb->andWhere('i.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['contactId'])) {
            $qb->andWhere('CAST(i.contact AS STRING) = :contactId')
                ->setParameter('contactId', $filters['contactId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere(
                'i.number LIKE :search
                 OR LOWER(i.clientNameSnapshot) LIKE :search
                 OR LOWER(i.clientReference) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($filters['search']) . '%');
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('i.issueDate >= :from')->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $qb->andWhere('i.issueDate <= :to')->setParameter('to', $filters['to']);
        }

        if (!empty($filters['overdue'])) {
            $qb->andWhere('i.status = :overdueStatus')
                ->andWhere('i.dueDate < :today')
                ->setParameter('overdueStatus', InvoiceStatus::ACKNOWLEDGED->value)
                ->setParameter('today', new \DateTimeImmutable('today'));
        }
    }
}
