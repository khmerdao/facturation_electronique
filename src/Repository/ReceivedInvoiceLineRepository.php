<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReceivedInvoice;
use App\Entity\ReceivedInvoiceLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReceivedInvoiceLine>
 */
class ReceivedInvoiceLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReceivedInvoiceLine::class);
    }

    /**
     * Retourne les lignes d'une facture reçue.
     * Utile pour l'affichage détaillé sur /received-invoices/{id}.
     */
    public function findByReceivedInvoice(ReceivedInvoice $invoice): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.receivedInvoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getResult();
    }
}
