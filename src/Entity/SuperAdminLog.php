<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SuperAdminLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal des actions super-admin (cross-tenant). N'utilise PAS
 * TenantAwareTrait : ces actions sont hors périmètre tenant et ne doivent
 * pas être filtrées par le TenantFilter. Rétention 5 ans.
 * Le tenant ciblé est référencé en SET NULL (conservation du log même si le
 * tenant est supprimé).
 */
#[ORM\Entity(repositoryClass: SuperAdminLogRepository::class)]
#[ORM\Table(name: 'super_admin_logs')]
#[ORM\Index(name: 'idx_sa_log_admin', columns: ['super_admin_id'])]
#[ORM\Index(name: 'idx_sa_log_target', columns: ['target_tenant_id'])]
#[ORM\Index(name: 'idx_sa_log_action', columns: ['action'])]
class SuperAdminLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'super_admin_id', nullable: false, onDelete: 'RESTRICT')]
    private ?User $superAdmin = null;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'target_tenant_id', nullable: true, onDelete: 'SET NULL')]
    private ?Tenant $targetTenant = null;

    /** Conserve le nom du tenant même après suppression. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetTenantName = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSuperAdmin(): ?User
    {
        return $this->superAdmin;
    }

    public function setSuperAdmin(?User $superAdmin): self
    {
        $this->superAdmin = $superAdmin;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getTargetTenant(): ?Tenant
    {
        return $this->targetTenant;
    }

    public function setTargetTenant(?Tenant $targetTenant): self
    {
        $this->targetTenant = $targetTenant;
        if (null !== $targetTenant) {
            $this->targetTenantName = $targetTenant->getName();
        }

        return $this;
    }

    public function getTargetTenantName(): ?string
    {
        return $this->targetTenantName;
    }

    public function setTargetTenantName(?string $targetTenantName): self
    {
        $this->targetTenantName = $targetTenantName;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
