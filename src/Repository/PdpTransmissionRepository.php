<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\PdpTransmissionStatus;
use App\Entity\Invoice;
use App\Entity\PdpTransmission;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PdpTransmission>
 */
class PdpTransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PdpTransmission::class);
    }

    /**
     * Retourne les transmissions d'une facture, de la plus récente à la plus ancienne.
     * Utilisé sur /invoices/{id} pour afficher la timeline de transmission PDP.
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les transmissions en erreur d'un tenant.
     * Utilisé pour l'alerte "PDP en erreur" sur le dashboard et /settings/pdp.
     */
    public function findErrors(Tenant $tenant): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.tenant = :tenant')
            ->andWhere('t.status = :status')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', PdpTransmissionStatus::ERROR)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les transmissions en attente d'envoi (PENDING).
     * Traitées par SendInvoiceToPdpJob, toutes les 30 secondes.
     *
     * @return PdpTransmission[]
     */
    public function findPending(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', PdpTransmissionStatus::PENDING)
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le taux de succès des transmissions d'un tenant sur une période.
     * Utilisé dans /settings/pdp et /admin/logs pour le monitoring.
     *
     * @return array{sent: int, acknowledged: int, rejected: int, error: int, successRate: float}
     */
    public function getSuccessStats(Tenant $tenant, \DateTimeImmutable $since): array
    {
        $raw = $this->createQueryBuilder('t')
            ->select('t.status AS status, COUNT(t.id) AS cnt')
            ->where('t.tenant = :tenant')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('tenant', $tenant)
            ->setParameter('since', $since)
            ->groupBy('t.status')
            ->getQuery()
            ->getArrayResult();

        $stats = ['sent' => 0, 'acknowledged' => 0, 'rejected' => 0, 'error' => 0];
        foreach ($raw as $row) {
            $stats[strtolower($row['status'])] = (int) $row['cnt'];
        }
        $total = array_sum($stats);
        $stats['successRate'] = $total > 0
            ? round($stats['acknowledged'] / $total * 100, 1)
            : 100.0;

        return $stats;
    }

    /**
     * Retourne toutes les transmissions d'un tenant (vue cross pour super-admin).
     * Note : nécessite que le TenantFilter soit désactivé pour le super-admin.
     */
    public function findAllTenants(
        int $page = 1,
        int $perPage = 50,
        ?PdpTransmissionStatus $status = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($status) {
            $qb->where('t.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve une transmission par son identifiant externe PDP.
     */
    public function findByExternalId(string $externalId): ?\App\Entity\PdpTransmission
    {
        return $this->createQueryBuilder('t')
            ->where('t.externalId = :externalId')
            ->setParameter('externalId', $externalId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
