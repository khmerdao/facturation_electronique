<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PdpTransmissionStatus;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\PdpTransmissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Tentative de transmission d'une facture émise vers la PDP/PPF.
 * Plusieurs transmissions possibles par facture (retries après rejet).
 * Trace le cycle complet : PENDING → SENT → ACKNOWLEDGED / REJECTED / ERROR.
 */
#[ORM\Entity(repositoryClass: PdpTransmissionRepository::class)]
#[ORM\Table(name: 'pdp_transmissions')]
#[ORM\Index(name: 'idx_transmission_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_transmission_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_transmission_external', columns: ['external_id'])]
class PdpTransmission
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'transmissions')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'string', enumType: PdpTransmissionStatus::class, options: ['default' => 'PENDING'])]
    private PdpTransmissionStatus $status = PdpTransmissionStatus::PENDING;

    /** Identifiant attribué par la PDP (lifecycle id). */
    #[ORM\Column(length: 191, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $pdpName = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attempt = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $rejectCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    /** Réponse brute de la PDP (pour audit / debug). */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawResponse = null;

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

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getStatus(): PdpTransmissionStatus
    {
        return $this->status;
    }

    public function setStatus(PdpTransmissionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getPdpName(): ?string
    {
        return $this->pdpName;
    }

    public function setPdpName(?string $pdpName): self
    {
        $this->pdpName = $pdpName;

        return $this;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function setAttempt(int $attempt): self
    {
        $this->attempt = $attempt;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): self
    {
        $this->acknowledgedAt = $acknowledgedAt;

        return $this;
    }

    public function getRejectCode(): ?string
    {
        return $this->rejectCode;
    }

    public function setRejectCode(?string $rejectCode): self
    {
        $this->rejectCode = $rejectCode;

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

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }

    public function setRawResponse(?array $rawResponse): self
    {
        $this->rawResponse = $rawResponse;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
