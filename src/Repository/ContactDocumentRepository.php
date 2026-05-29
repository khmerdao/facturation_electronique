<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\ContactDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactDocument>
 */
class ContactDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactDocument::class);
    }

    /**
     * Retourne les pièces jointes d'un contact, triées par date décroissante.
     * Utilisé dans l'onglet "Documents" de la fiche contact.
     */
    public function findByContact(Contact $contact): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le volume total de stockage utilisé par un contact (en octets).
     * Utilisé pour le quota S3 du tenant.
     */
    public function sumStorageByContact(Contact $contact): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('SUM(d.size)')
            ->where('d.contact = :contact')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
