<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceLine>
 */
class InvoiceLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceLine::class);
    }

    /**
     * Retourne les lignes d'une facture dans l'ordre d'affichage (position ASC).
     * En général inutile (Invoice::getLines() suffit), mais utile pour les
     * requêtes batch (export FEC, recalcul de totaux).
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.invoice = :invoice')
            ->orderBy('l.position', 'ASC')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la prochaine position disponible pour une ligne.
     * Appelé lors de l'ajout d'une ligne dans l'éditeur de facture.
     */
    public function getNextPosition(Invoice $invoice): int
    {
        $max = $this->createQueryBuilder('l')
            ->select('MAX(l.position)')
            ->where('l.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getSingleScalarResult();

        return ($max ?? -1) + 1;
    }
}
