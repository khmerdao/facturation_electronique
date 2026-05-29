<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : GenerateInvoicePdfMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class GenerateInvoicePdfMessage
{
    public function __construct(
        private readonly string $invoiceId,
    ) {}

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }
}
