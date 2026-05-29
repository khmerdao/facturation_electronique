<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceSequence>
 */
class InvoiceSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceSequence::class);
    }

    /**
     * Retourne toutes les séquences d'un tenant.
     * Utilisé sur /settings/sequences.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Acquiert un verrou pessimiste (PESSIMISTIC_WRITE) sur la séquence
     * pour garantir l'absence de trou dans la numérotation (art. 242 nonies A).
     * Doit être appelé dans une transaction ouverte.
     */
    public function lockForUpdate(InvoiceSequence $sequence): InvoiceSequence
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->setParameter('id', $sequence->getId())
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getSingleResult();
    }

    /**
     * Retourne la séquence par défaut des factures d'un tenant.
     * La séquence par défaut est la plus ancienne non-crédit-note.
     * Si aucune n'existe, retourne null (le service la créera).
     */
    public function findDefaultForInvoice(Tenant $tenant): ?InvoiceSequence
    {
        return $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.isCreditNoteSequence = FALSE')
            ->setParameter('tenant', $tenant)
            ->orderBy('s.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne la séquence par défaut des avoirs.
     */
    public function findDefaultForCreditNote(Tenant $tenant): ?InvoiceSequence
    {
        return $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.isCreditNoteSequence = TRUE')
            ->setParameter('tenant', $tenant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
