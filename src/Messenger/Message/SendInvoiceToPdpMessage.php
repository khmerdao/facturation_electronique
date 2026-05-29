<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendInvoiceToPdpMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendInvoiceToPdpMessage
{
    public function __construct(
        private readonly string $invoiceId,
    ) {}

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }
}
