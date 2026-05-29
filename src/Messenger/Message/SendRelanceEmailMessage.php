<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendRelanceEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendRelanceEmailMessage
{
    public function __construct(
        private readonly string $invoiceId,
        private readonly int $level,
    ) {}

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function getLevel(): int
    {
        return $this->level;
    }
}
