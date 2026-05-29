<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : AggregateEReportingTransactionsMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class AggregateEReportingTransactionsMessage
{
    public function __construct(
        private readonly string $batchId,
    ) {}

    public function getBatchId(): string
    {
        return $this->batchId;
    }
}
