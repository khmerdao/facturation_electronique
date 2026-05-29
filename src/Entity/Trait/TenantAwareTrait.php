<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\Tenant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mutualise la relation ManyToOne vers Tenant sur toutes les entités métier.
 * Le TenantFilter (Doctrine SQL Filter) applique automatiquement
 * WHERE tenant_id = :current_tenant sur ces entités.
 */
trait TenantAwareTrait
{
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): self
    {
        $this->tenant = $tenant;

        return $this;
    }
}
