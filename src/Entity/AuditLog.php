<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit immuable (INSERT ONLY — jamais UPDATE/DELETE).
 * Trace toutes les actions métier d'un tenant pour 10 ans (obligation légale).
 * Le flag isImpersonated indique une action effectuée par un super-admin
 * connecté en tant que le tenant (transparence).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_tenant_created', columns: ['tenant_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
class AuditLog
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Null = action système (worker, webhook). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payloadBefore = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payloadAfter = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isImpersonated = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $impersonatedBy = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getPayloadBefore(): ?array
    {
        return $this->payloadBefore;
    }

    public function setPayloadBefore(?array $payloadBefore): self
    {
        $this->payloadBefore = $payloadBefore;

        return $this;
    }

    public function getPayloadAfter(): ?array
    {
        return $this->payloadAfter;
    }

    public function setPayloadAfter(?array $payloadAfter): self
    {
        $this->payloadAfter = $payloadAfter;

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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function isImpersonated(): bool
    {
        return $this->isImpersonated;
    }

    public function setIsImpersonated(bool $isImpersonated): self
    {
        $this->isImpersonated = $isImpersonated;

        return $this;
    }

    public function getImpersonatedBy(): ?string
    {
        return $this->impersonatedBy;
    }

    public function setImpersonatedBy(?string $impersonatedBy): self
    {
        $this->impersonatedBy = $impersonatedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
