<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPriceHistory>
 */
class ProductPriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPriceHistory::class);
    }

    /**
     * Retourne l'historique des prix d'un produit, du plus récent au plus ancien.
     * Utilisé dans l'onglet "Historique des prix" de la fiche produit /products/{id}.
     */
    public function findByProduct(Product $product, int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.product = :product')
            ->setParameter('product', $product)
            ->orderBy('h.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le prix en vigueur à une date donnée (dernier changement avant cette date).
     * Utilisé pour l'analyse rétrospective du catalogue.
     */
    public function findPriceAt(Product $product, \DateTimeImmutable $at): ?ProductPriceHistory
    {
        return $this->createQueryBuilder('h')
            ->where('h.product = :product')
            ->andWhere('h.changedAt <= :at')
            ->setParameter('product', $product)
            ->setParameter('at', $at)
            ->orderBy('h.changedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
