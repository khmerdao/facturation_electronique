<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp ajouté à chaque Envelope lors du dispatch d'un message.
 * Permet au TenantContextMiddleware de rehydrater le contexte tenant
 * dans les workers, qui tournent hors contexte HTTP.
 *
 * Usage dans un service :
 *   $this->bus->dispatch(
 *       new GenerateInvoicePdfMessage($invoiceId),
 *       [new TenantStamp($tenant->getId(), $user->getId())]
 *   );
 */
final class TenantStamp implements StampInterface
{
    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $userId = null,
    ) {}

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }
}
