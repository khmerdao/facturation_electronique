<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : CheckOverdueInvoicesMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class CheckOverdueInvoicesMessage
{
    public function __construct(
        private readonly string $tenantId,
    ) {}

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
