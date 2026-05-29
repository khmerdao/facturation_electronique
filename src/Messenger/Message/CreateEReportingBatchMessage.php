<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : CreateEReportingBatchMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class CreateEReportingBatchMessage
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $period,
    ) {}

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }
}
