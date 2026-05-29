<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\ContactPerson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactPerson>
 */
class ContactPersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactPerson::class);
    }

    /**
     * Retourne tous les interlocuteurs d'un contact, triés par nom.
     * Utilisé sur la fiche contact /contacts/{id}.
     */
    public function findByContact(Contact $contact): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
