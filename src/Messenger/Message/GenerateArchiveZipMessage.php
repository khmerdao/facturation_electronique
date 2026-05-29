<?php

declare(strict_types=1);

namespace App\Messenger\Message;

/**
 * Message Messenger : GenerateArchiveZipMessage.
 * Dispatché de manière asynchrone — voir messenger.yaml pour le routing.
 */
final class GenerateArchiveZipMessage
{
    public function __construct(
        private readonly string $exportJobId,
    ) {}

    public function getExportJobId(): string
    {
        return $this->exportJobId;
    }
}
