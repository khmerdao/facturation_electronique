<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EReportingCorrectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Correction apportée à un batch déjà soumis (rectification d'une période
 * antérieure). Trace l'ancienne et la nouvelle valeur + le motif.
 */
#[ORM\Entity(repositoryClass: EReportingCorrectionRepository::class)]
#[ORM\Table(name: 'ereporting_corrections')]
#[ORM\Index(name: 'idx_er_correction_batch', columns: ['batch_id'])]
class EReportingCorrection
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: EReportingBatch::class, inversedBy: 'corrections')]
    #[ORM\JoinColumn(name: 'batch_id', nullable: false, onDelete: 'CASCADE')]
    private ?EReportingBatch $batch = null;

    #[ORM\Column(length: 50)]
    private string $field;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'corrected_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $correctedBy = null;

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

    public function getBatch(): ?EReportingBatch
    {
        return $this->batch;
    }

    public function setBatch(?EReportingBatch $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): self
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): self
    {
        $this->newValue = $newValue;

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

    public function getCorrectedBy(): ?User
    {
        return $this->correctedBy;
    }

    public function setCorrectedBy(?User $correctedBy): self
    {
        $this->correctedBy = $correctedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
