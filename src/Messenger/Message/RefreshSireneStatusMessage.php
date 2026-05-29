<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : RefreshSireneStatusMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class RefreshSireneStatusMessage
{
    public function __construct(
        private readonly string $contactId,
    ) {}

    public function getContactId(): string
    {
        return $this->contactId;
    }
}
