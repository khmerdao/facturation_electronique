<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendNotificationMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendNotificationMessage
{
    public function __construct(
        private readonly string $notificationId,
    ) {}

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }
}
