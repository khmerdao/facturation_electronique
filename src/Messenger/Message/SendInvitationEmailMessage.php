<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : SendInvitationEmailMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class SendInvitationEmailMessage
{
    public function __construct(
        private readonly string $invitationId,
    ) {}

    public function getInvitationId(): string
    {
        return $this->invitationId;
    }
}
