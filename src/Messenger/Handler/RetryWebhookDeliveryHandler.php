<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\RetryWebhookDeliveryMessage;
use App\Messenger\Message\DeliverWebhookMessage;
use App\Repository\WebhookDeliveryRepository;
use App\Entity\Enum\WebhookDeliveryStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RetryWebhookDeliveryHandler
{
    private const MAX_RETRIES = 5;

    public function __construct(
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(RetryWebhookDeliveryMessage $message): void
    {
        $delivery = $this->deliveryRepository->find($message->getDeliveryId());
        if (!$delivery) return;

        // Plus de 5 tentatives : abandonner (ne pas redispatching)

        // Re-dispatcher la livraison
        $this->bus->dispatch(new DeliverWebhookMessage($message->getDeliveryId()));
    }
}
