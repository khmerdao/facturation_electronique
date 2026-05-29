<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendPaymentConfirmationEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendPaymentConfirmationEmailMessage
{
    public function __construct(
        private readonly string $paymentId,
    ) {}

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
