<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Security\TenantContext;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose la variable globale `tenantContext` dans tous les templates Twig.
 *
 * Usage dans les templates :
 *   {{ tenantContext.tenant.name }}
 *   {{ tenantContext.membership.role.value }}
 *   {% if tenantContext.isInitialized() %}...{% endif %}
 */
final class TenantContextExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function getGlobals(): array
    {
        return [
            'tenantContext' => $this->tenantContext,
        ];
    }
}
