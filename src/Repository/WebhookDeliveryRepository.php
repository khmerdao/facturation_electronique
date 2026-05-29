<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\WebhookDeliveryStatus;
use App\Entity\WebhookDelivery;
use App\Entity\WebhookEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookDelivery>
 */
class WebhookDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    /**
     * Retourne les 50 dernières livraisons d'un endpoint (débogage).
     * Affiché dans /settings/integrations → détail de l'endpoint.
     */
    public function findRecentByEndpoint(WebhookEndpoint $endpoint, int $limit = 50): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.endpoint = :endpoint')
            ->setParameter('endpoint', $endpoint)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les livraisons en échec éligibles à un nouveau retry.
     * Critère : nextRetryAt <= maintenant.
     * Traité par WebhookRetryJob (Messenger scheduled toutes les minutes).
     *
     * @return WebhookDelivery[]
     */
    public function findDueRetries(int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.nextRetryAt <= :now')
            ->setParameter('status', WebhookDeliveryStatus::FAILED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('d.nextRetryAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
