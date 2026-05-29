<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\EReportingPeriodicity;
use App\Entity\Enum\EReportingStatus;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\EReportingBatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Lot d'e-reporting pour une période donnée (transmission des données de
 * transaction B2C/international + données de paiement à la DGFiP).
 * Un seul batch par tenant et par période (UniqueConstraint).
 */
#[ORM\Entity(repositoryClass: EReportingBatchRepository::class)]
#[ORM\Table(name: 'ereporting_batches')]
#[ORM\UniqueConstraint(name: 'uniq_batch_tenant_period', columns: ['tenant_id', 'period'])]
#[ORM\Index(name: 'idx_batch_tenant_status', columns: ['tenant_id', 'status'])]
class EReportingBatch
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Période au format YYYY-MM (mensuel) ou YYYY-Qn (trimestriel). */
    #[ORM\Column(length: 7)]
    private string $period;

    #[ORM\Column(type: 'string', enumType: EReportingPeriodicity::class, options: ['default' => 'MONTHLY'])]
    private EReportingPeriodicity $periodicity = EReportingPeriodicity::MONTHLY;

    #[ORM\Column(type: 'string', enumType: EReportingStatus::class, options: ['default' => 'NOT_STARTED'])]
    private EReportingStatus $status = EReportingStatus::NOT_STARTED;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $deadline;

    #[ORM\Column(options: ['default' => false])]
    private bool $late = false;

    /** Déclaration néant (aucune transaction sur la période). */
    #[ORM\Column(options: ['default' => false])]
    private bool $isNil = false;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $dgfipReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $xmlS3Key = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, EReportingTransaction> */
    #[ORM\OneToMany(targetEntity: EReportingTransaction::class, mappedBy: 'batch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $transactions;

    /** @var Collection<int, EReportingPaymentLine> */
    #[ORM\OneToMany(targetEntity: EReportingPaymentLine::class, mappedBy: 'batch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $paymentLines;

    /** @var Collection<int, EReportingCorrection> */
    #[ORM\OneToMany(targetEntity: EReportingCorrection::class, mappedBy: 'batch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $corrections;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->deadline = new \DateTimeImmutable();
        $this->transactions = new ArrayCollection();
        $this->paymentLines = new ArrayCollection();
        $this->corrections = new ArrayCollection();
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

    public function getPeriodicity(): EReportingPeriodicity
    {
        return $this->periodicity;
    }

    public function setPeriodicity(EReportingPeriodicity $periodicity): self
    {
        $this->periodicity = $periodicity;

        return $this;
    }

    public function getStatus(): EReportingStatus
    {
        return $this->status;
    }

    public function setStatus(EReportingStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDeadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeImmutable $deadline): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function isLate(): bool
    {
        return $this->late;
    }

    public function setLate(bool $late): self
    {
        $this->late = $late;

        return $this;
    }

    public function isNil(): bool
    {
        return $this->isNil;
    }

    public function setIsNil(bool $isNil): self
    {
        $this->isNil = $isNil;

        return $this;
    }

    public function getDgfipReference(): ?string
    {
        return $this->dgfipReference;
    }

    public function setDgfipReference(?string $dgfipReference): self
    {
        $this->dgfipReference = $dgfipReference;

        return $this;
    }

    public function getXmlS3Key(): ?string
    {
        return $this->xmlS3Key;
    }

    public function setXmlS3Key(?string $xmlS3Key): self
    {
        $this->xmlS3Key = $xmlS3Key;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): self
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): self
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function setRejectReason(?string $rejectReason): self
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, EReportingTransaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(EReportingTransaction $transaction): self
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setBatch($this);
        }

        return $this;
    }

    /** @return Collection<int, EReportingPaymentLine> */
    public function getPaymentLines(): Collection
    {
        return $this->paymentLines;
    }

    public function addPaymentLine(EReportingPaymentLine $line): self
    {
        if (!$this->paymentLines->contains($line)) {
            $this->paymentLines->add($line);
            $line->setBatch($this);
        }

        return $this;
    }

    /** @return Collection<int, EReportingCorrection> */
    public function getCorrections(): Collection
    {
        return $this->corrections;
    }

    public function addCorrection(EReportingCorrection $correction): self
    {
        if (!$this->corrections->contains($correction)) {
            $this->corrections->add($correction);
            $correction->setBatch($this);
        }

        return $this;
    }
}
