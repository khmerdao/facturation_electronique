<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\Role;
use App\Repository\TenantMembershipRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité de liaison explicite User <-> Tenant (remplace un ManyToMany).
 * Porte le rôle de l'utilisateur dans le tenant + les dates d'invitation/jonction.
 * Contrainte d'unicité : un utilisateur ne peut avoir qu'un seul membership
 * par tenant.
 */
#[ORM\Entity(repositoryClass: TenantMembershipRepository::class)]
#[ORM\Table(name: 'tenant_memberships')]
#[ORM\UniqueConstraint(name: 'uniq_user_tenant', columns: ['user_id', 'tenant_id'])]
#[ORM\Index(name: 'idx_membership_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_membership_user', columns: ['user_id'])]
class TenantMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'tenant_id', nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: 'string', enumType: Role::class)]
    private Role $role;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $invitedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $joinedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->invitedAt = new \DateTimeImmutable();
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

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): self
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getInvitedAt(): \DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(?\DateTimeImmutable $joinedAt): self
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function isActive(): bool
    {
        return null !== $this->joinedAt;
    }
}
