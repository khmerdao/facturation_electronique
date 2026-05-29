<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceTemplate;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceTemplate>
 */
class InvoiceTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceTemplate::class);
    }

    /**
     * Retourne tous les templates d'un tenant triés par nom.
     * Utilisé sur /settings/templates.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('t.isDefault', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le template par défaut du tenant.
     * Pré-sélectionné lors de la création d'une nouvelle facture.
     */
    public function findDefault(Tenant $tenant): ?InvoiceTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.tenant = :tenant')
            ->andWhere('t.isDefault = TRUE')
            ->setParameter('tenant', $tenant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
