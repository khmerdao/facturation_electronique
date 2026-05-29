<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendWelcomeEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendWelcomeEmailMessage
{
    public function __construct(
        private readonly string $userId,
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }
}
