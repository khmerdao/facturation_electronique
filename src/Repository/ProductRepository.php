<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\ProductType;
use App\Entity\Product;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Retourne tous les produits actifs d'un tenant triés par référence.
     * Utilisé sur /products et dans le sélecteur de ligne de facture.
     */
    public function findAllActive(Tenant $tenant): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.active = TRUE')
            ->setParameter('tenant', $tenant)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les produits de type SERVICE.
     * Pour l'e-reporting : les services sont soumis à TVA sur encaissement.
     */
    public function findServices(Tenant $tenant): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.active = TRUE')
            ->andWhere('p.type = :type')
            ->setParameter('tenant', $tenant)
            ->setParameter('type', ProductType::SERVICE)
            ->orderBy('p.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche un produit par sa référence exacte dans un tenant.
     * Utilisé pour la validation d'unicité lors de la création/modification.
     */
    public function findByReference(string $reference, Tenant $tenant): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.reference = :reference')
            ->setParameter('tenant', $tenant)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche full-text sur référence, libellé et description.
     * Utilisé dans l'autocomplétion du sélecteur de ligne de facture (Vue 3).
     *
     * @return Product[]
     */
    public function search(Tenant $tenant, string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.active = TRUE')
            ->andWhere(
                'LOWER(p.reference) LIKE :q
                 OR LOWER(p.label) LIKE :q
                 OR LOWER(p.description) LIKE :q'
            )
            ->setParameter('tenant', $tenant)
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setMaxResults($limit)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les produits les plus utilisés dans les factures (top N).
     * Utilisé sur /products pour mettre en avant les articles fréquents.
     */
    public function findMostUsed(Tenant $tenant, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(il.id) AS usageCount')
            ->join('App\Entity\InvoiceLine', 'il', 'WITH', 'il.product = p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.active = TRUE')
            ->setParameter('tenant', $tenant)
            ->groupBy('p.id')
            ->orderBy('usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les produits actifs d'un tenant.
     * Utilisé pour les quotas du plan (limite catalogue).
     */
    public function countActive(Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.tenant = :tenant')
            ->andWhere('p.active = TRUE')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
