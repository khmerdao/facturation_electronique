<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendPasswordResetEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendPasswordResetEmailMessage
{
    public function __construct(
        private readonly string $userId,
        private readonly string $token,
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
