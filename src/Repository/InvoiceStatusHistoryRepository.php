<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\InvoiceStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceStatusHistory>
 */
class InvoiceStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceStatusHistory::class);
    }

    /**
     * Retourne la timeline de cycle de vie d'une facture (du premier au dernier événement).
     * Affichée sur /invoices/{id} dans la section "Historique".
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
