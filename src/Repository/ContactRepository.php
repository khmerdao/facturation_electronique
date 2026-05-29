<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Enum\ContactType;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Retourne tous les contacts actifs d'un tenant triés par nom.
     * Utilisé sur la page /contacts (liste unifiée clients + fournisseurs).
     */
    public function findAllActive(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.active = TRUE')
            ->setParameter('tenant', $tenant)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les clients actifs (type CLIENT ou BOTH).
     * Utilisé dans le sélecteur client de l'éditeur de facture.
     */
    public function findClients(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.active = TRUE')
            ->andWhere('c.type IN (:types)')
            ->setParameter('tenant', $tenant)
            ->setParameter('types', [ContactType::CLIENT->value, ContactType::BOTH->value])
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les fournisseurs actifs (type SUPPLIER ou BOTH).
     * Utilisé dans la liste des factures reçues et /contacts (filtre).
     */
    public function findSuppliers(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.active = TRUE')
            ->andWhere('c.type IN (:types)')
            ->setParameter('tenant', $tenant)
            ->setParameter('types', [ContactType::SUPPLIER->value, ContactType::BOTH->value])
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche un contact par son SIRET dans un tenant.
     * Utilisé pour le rapprochement automatique lors de la réception d'une
     * facture PDP (matching fournisseur → Contact existant).
     */
    public function findBySiret(string $siret, Tenant $tenant): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.siret = :siret')
            ->setParameter('tenant', $tenant)
            ->setParameter('siret', $siret)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche full-text sur nom, SIRET, email, identifiant PDP.
     * Utilisé dans la barre de recherche de /contacts et le sélecteur
     * client de l'éditeur de facture (autocomplétion).
     *
     * @return Contact[]
     */
    public function search(Tenant $tenant, string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.active = TRUE')
            ->andWhere(
                'LOWER(c.name) LIKE :q
                 OR c.siret LIKE :q
                 OR LOWER(c.email) LIKE :q
                 OR c.pdpIdentifier LIKE :q'
            )
            ->setParameter('tenant', $tenant)
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setMaxResults($limit)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les contacts dont le statut Sirene doit être re-vérifié.
     * Critères : jamais vérifié (sireneCheckedAt IS NULL) OU
     * dernière vérification > 30 jours. Utilisé par RefreshSireneStatusJob.
     *
     * @return Contact[]
     */
    public function findDueSireneRefresh(int $limit = 100): array
    {
        $threshold = new \DateTimeImmutable('-30 days');

        return $this->createQueryBuilder('c')
            ->where('c.siret IS NOT NULL')
            ->andWhere('c.active = TRUE')
            ->andWhere('c.sireneCheckedAt IS NULL OR c.sireneCheckedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les contacts avec leurs statistiques (nb de factures, CA).
     * Utilisé sur la fiche contact /contacts/{id} pour le résumé d'activité.
     */
    public function findWithStats(Contact $contact): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c',
                'COUNT(DISTINCT i.id) AS invoiceCount',
                'SUM(CASE WHEN i.status = \'PAID\' THEN i.totalTtc ELSE 0 END) AS totalPaid',
            )
            ->leftJoin('App\Entity\Invoice', 'i', 'WITH', 'i.contact = c')
            ->where('c.id = :id')
            ->setParameter('id', $contact->getId())
            ->groupBy('c.id')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte les contacts actifs d'un tenant.
     * Utilisé pour vérifier les limites du plan (usage/quota).
     */
    public function countActive(Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.tenant = :tenant')
            ->andWhere('c.active = TRUE')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
