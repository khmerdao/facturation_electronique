<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\TaxAdjustmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ajustement de TVA manuel (TVA collectée ou déductible) sur une période,
 * pour les cas non couverts par les factures (régularisations, TVA sur
 * immobilisations, autoliquidation import…). Alimente l'aide à la CA3.
 */
#[ORM\Entity(repositoryClass: TaxAdjustmentRepository::class)]
#[ORM\Table(name: 'tax_adjustments')]
#[ORM\Index(name: 'idx_tax_adj_tenant_period', columns: ['tenant_id', 'period'])]
class TaxAdjustment
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Période concernée (YYYY-MM). */
    #[ORM\Column(length: 7)]
    private string $period;

    /** collected | deductible */
    #[ORM\Column(length: 12)]
    private string $type;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $tvaRate = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $amount;

    /** Ligne CA3 visée (ex : "08", "20", "0979"). */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $ca3Box = null;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

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

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function setPeriod(string $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTvaRate(): ?string
    {
        return $this->tvaRate;
    }

    public function setTvaRate(?string $tvaRate): self
    {
        $this->tvaRate = $tvaRate;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCa3Box(): ?string
    {
        return $this->ca3Box;
    }

    public function setCa3Box(?string $ca3Box): self
    {
        $this->ca3Box = $ca3Box;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

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
}
