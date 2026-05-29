<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendInvoiceEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendInvoiceEmailMessage
{
    public function __construct(
        private readonly string $invoiceId,
        private readonly string $recipientEmail,
    ) {}

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
}
