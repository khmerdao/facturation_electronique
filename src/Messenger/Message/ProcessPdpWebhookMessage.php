<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : ProcessPdpWebhookMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class ProcessPdpWebhookMessage
{
    public function __construct(
        private readonly string $webhookLogId,
    ) {}

    public function getWebhookLogId(): string
    {
        return $this->webhookLogId;
    }
}
