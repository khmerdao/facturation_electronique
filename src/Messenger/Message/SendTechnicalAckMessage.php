<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendTechnicalAckMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendTechnicalAckMessage
{
    public function __construct(
        private readonly string $receivedInvoiceId,
    ) {}

    public function getReceivedInvoiceId(): string
    {
        return $this->receivedInvoiceId;
    }
}
