<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendDigestEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendDigestEmailMessage
{
    public function __construct(
        private readonly string $userId,
        private readonly string $tenantId,
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
