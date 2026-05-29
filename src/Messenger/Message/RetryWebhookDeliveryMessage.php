<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : RetryWebhookDeliveryMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class RetryWebhookDeliveryMessage
{
    public function __construct(
        private readonly string $deliveryId,
    ) {}

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }
}
