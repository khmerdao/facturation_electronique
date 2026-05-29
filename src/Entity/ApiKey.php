<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ApiEnvironment;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Clé d'API pour l'accès programmatique. Seul le hash de la clé est stocké
 * (la clé en clair n'est montrée qu'une fois, à la création). Le préfixe
 * permet d'identifier la clé dans l'UI sans révéler le secret.
 */
#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_keys')]
#[ORM\Index(name: 'idx_apikey_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_apikey_hash', columns: ['key_hash'])]
class ApiKey
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    /** Hash SHA-256 de la clé complète. */
    #[ORM\Column(length: 64, unique: true)]
    private string $keyHash;

    /** Préfixe visible (ex : "fe_live_a1b2…"). */
    #[ORM\Column(length: 20)]
    private string $keyPrefix;

    #[ORM\Column(type: 'string', enumType: ApiEnvironment::class, options: ['default' => 'TEST'])]
    private ApiEnvironment $environment = ApiEnvironment::TEST;

    /** Permissions accordées (scopes), ex : ["invoices:read","invoices:write"]. */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): self
    {
        $this->keyHash = $keyHash;

        return $this;
    }

    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function setKeyPrefix(string $keyPrefix): self
    {
        $this->keyPrefix = $keyPrefix;

        return $this;
    }

    public function getEnvironment(): ApiEnvironment
    {
        return $this->environment;
    }

    public function setEnvironment(ApiEnvironment $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function isActive(): bool
    {
        $now = new \DateTimeImmutable();

        return null === $this->revokedAt
            && (null === $this->expiresAt || $this->expiresAt > $now);
    }
}
