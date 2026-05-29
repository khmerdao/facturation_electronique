<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : DeliverWebhookMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class DeliverWebhookMessage
{
    public function __construct(
        private readonly string $deliveryId,
    ) {}

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }
}
