<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : GenerateExportCsvMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class GenerateExportCsvMessage
{
    public function __construct(
        private readonly string $exportJobId,
    ) {}

    public function getExportJobId(): string
    {
        return $this->exportJobId;
    }
}
