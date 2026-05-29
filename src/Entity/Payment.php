<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PaymentDirection;
use App\Entity\Enum\PaymentMode;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Règlement rattaché soit à une facture émise (encaissement), soit à une
 * facture reçue (décaissement) — exactement l'un des deux est renseigné.
 * Pour les prestations de services, le paiement déclenche l'exigibilité TVA :
 * il est rapporté à la DGFiP via l'e-reporting paiement (codes normalisés).
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payment_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_payment_received', columns: ['received_invoice_id'])]
#[ORM\Index(name: 'idx_payment_tenant_date', columns: ['tenant_id', 'date'])]
#[ORM\Index(name: 'idx_payment_direction', columns: ['direction'])]
class Payment
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Facture émise réglée (encaissement). Null si décaissement. */
    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: true, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    /** Facture reçue réglée (décaissement). Null si encaissement. */
    #[ORM\ManyToOne(targetEntity: ReceivedInvoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'received_invoice_id', nullable: true, onDelete: 'CASCADE')]
    private ?ReceivedInvoice $receivedInvoice = null;

    #[ORM\Column(type: 'string', enumType: PaymentDirection::class)]
    private PaymentDirection $direction;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Montant converti en euros (si devise étrangère) pour la compta. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amountEur = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 6, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(type: 'string', enumType: PaymentMode::class)]
    private PaymentMode $mode;

    /** Code DGFiP figé au moment du paiement (dérivé de PaymentMode). */
    #[ORM\Column(length: 2)]
    private string $modeDgfipCode;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** Clé d'idempotence pour éviter les doubles saisies (import bancaire). */
    #[ORM\Column(length: 191, nullable: true, unique: true)]
    private ?string $idempotencyKey = null;

    // E-reporting paiement
    #[ORM\Column(options: ['default' => false])]
    private bool $ereportingRequired = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $ereportingReported = false;

    #[ORM\ManyToOne(targetEntity: EReportingBatch::class)]
    #[ORM\JoinColumn(name: 'ereporting_batch_id', nullable: true, onDelete: 'SET NULL')]
    private ?EReportingBatch $ereportingBatch = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recorded_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $recordedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->date = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getReceivedInvoice(): ?ReceivedInvoice
    {
        return $this->receivedInvoice;
    }

    public function setReceivedInvoice(?ReceivedInvoice $receivedInvoice): self
    {
        $this->receivedInvoice = $receivedInvoice;

        return $this;
    }

    public function getDirection(): PaymentDirection
    {
        return $this->direction;
    }

    public function setDirection(PaymentDirection $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getAmountEur(): ?string
    {
        return $this->amountEur;
    }

    public function setAmountEur(?string $amountEur): self
    {
        $this->amountEur = $amountEur;

        return $this;
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): self
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getMode(): PaymentMode
    {
        return $this->mode;
    }

    public function setMode(PaymentMode $mode): self
    {
        $this->mode = $mode;
        $this->modeDgfipCode = $mode->dgfipCode();

        return $this;
    }

    public function getModeDgfipCode(): string
    {
        return $this->modeDgfipCode;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function isEreportingRequired(): bool
    {
        return $this->ereportingRequired;
    }

    public function setEreportingRequired(bool $ereportingRequired): self
    {
        $this->ereportingRequired = $ereportingRequired;

        return $this;
    }

    public function isEreportingReported(): bool
    {
        return $this->ereportingReported;
    }

    public function setEreportingReported(bool $ereportingReported): self
    {
        $this->ereportingReported = $ereportingReported;

        return $this;
    }

    public function getEreportingBatch(): ?EReportingBatch
    {
        return $this->ereportingBatch;
    }

    public function setEreportingBatch(?EReportingBatch $ereportingBatch): self
    {
        $this->ereportingBatch = $ereportingBatch;

        return $this;
    }

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): self
    {
        $this->recordedBy = $recordedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
