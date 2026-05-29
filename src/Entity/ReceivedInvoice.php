<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\ReceivedInvoiceStatus;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\ReceivedInvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Facture reçue via PDP/PPF (Factur-X, UBL, CII). Parsée, archivée telle quelle
 * (fichier original = preuve). Le fournisseur est rapproché par SIRET.
 * external_pdp_id assure l'idempotence du webhook.
 */
#[ORM\Entity(repositoryClass: ReceivedInvoiceRepository::class)]
#[ORM\Table(name: 'received_invoices')]
#[ORM\UniqueConstraint(name: 'uniq_received_external_pdp', columns: ['tenant_id', 'external_pdp_id'])]
#[ORM\Index(name: 'idx_received_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_received_supplier', columns: ['supplier_contact_id'])]
class ReceivedInvoice
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', enumType: ReceivedInvoiceStatus::class, options: ['default' => 'PENDING_VALIDATION'])]
    private ReceivedInvoiceStatus $status = ReceivedInvoiceStatus::PENDING_VALIDATION;

    /** Identifiant côté PDP (idempotence webhook). */
    #[ORM\Column(length: 191, nullable: true)]
    private ?string $externalPdpId = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: 'supplier_contact_id', nullable: true, onDelete: 'SET NULL')]
    private ?Contact $supplierContact = null;

    // Données extraites du XML
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierName = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $supplierSiret = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $supplierTvaIntra = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $supplierIban = null;

    #[ORM\Column(type: 'string', enumType: InvoiceFormat::class, nullable: true)]
    private ?InvoiceFormat $format = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $invoiceDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amountHt = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amountTva = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amountTtc = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountPaid = '0.00';

    /** Données parsées complètes (JSON) + erreurs éventuelles. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parsedData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parseErrors = null;

    // Archivage (fichier original = preuve, WORM S3)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rawFileS3Key = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    /** Accusé de réception technique (obligatoire réforme sept. 2026). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $technicalAckSentAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contestReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contestDescription = null;

    /** @var Collection<int, ReceivedInvoiceLine> */
    #[ORM\OneToMany(targetEntity: ReceivedInvoiceLine::class, mappedBy: 'receivedInvoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'receivedInvoice')]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $payments;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->receivedAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStatus(): ReceivedInvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(ReceivedInvoiceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getExternalPdpId(): ?string
    {
        return $this->externalPdpId;
    }

    public function setExternalPdpId(?string $externalPdpId): self
    {
        $this->externalPdpId = $externalPdpId;

        return $this;
    }

    public function getSupplierContact(): ?Contact
    {
        return $this->supplierContact;
    }

    public function setSupplierContact(?Contact $supplierContact): self
    {
        $this->supplierContact = $supplierContact;

        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function setSupplierName(?string $supplierName): self
    {
        $this->supplierName = $supplierName;

        return $this;
    }

    public function getSupplierSiret(): ?string
    {
        return $this->supplierSiret;
    }

    public function setSupplierSiret(?string $supplierSiret): self
    {
        $this->supplierSiret = $supplierSiret;

        return $this;
    }

    public function getSupplierTvaIntra(): ?string
    {
        return $this->supplierTvaIntra;
    }

    public function setSupplierTvaIntra(?string $v): self
    {
        $this->supplierTvaIntra = $v;

        return $this;
    }

    public function getSupplierIban(): ?string
    {
        return $this->supplierIban;
    }

    public function setSupplierIban(?string $supplierIban): self
    {
        $this->supplierIban = $supplierIban;

        return $this;
    }

    public function getFormat(): ?InvoiceFormat
    {
        return $this->format;
    }

    public function setFormat(?InvoiceFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getInvoiceDate(): ?\DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTimeImmutable $invoiceDate): self
    {
        $this->invoiceDate = $invoiceDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;

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

    public function getAmountHt(): ?string
    {
        return $this->amountHt;
    }

    public function setAmountHt(?string $amountHt): self
    {
        $this->amountHt = $amountHt;

        return $this;
    }

    public function getAmountTva(): ?string
    {
        return $this->amountTva;
    }

    public function setAmountTva(?string $amountTva): self
    {
        $this->amountTva = $amountTva;

        return $this;
    }

    public function getAmountTtc(): ?string
    {
        return $this->amountTtc;
    }

    public function setAmountTtc(?string $amountTtc): self
    {
        $this->amountTtc = $amountTtc;

        return $this;
    }

    public function getAmountPaid(): string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(string $amountPaid): self
    {
        $this->amountPaid = $amountPaid;

        return $this;
    }

    public function getParsedData(): ?array
    {
        return $this->parsedData;
    }

    public function setParsedData(?array $parsedData): self
    {
        $this->parsedData = $parsedData;

        return $this;
    }

    public function getParseErrors(): ?array
    {
        return $this->parseErrors;
    }

    public function setParseErrors(?array $parseErrors): self
    {
        $this->parseErrors = $parseErrors;

        return $this;
    }

    public function getRawFileS3Key(): ?string
    {
        return $this->rawFileS3Key;
    }

    public function setRawFileS3Key(?string $rawFileS3Key): self
    {
        $this->rawFileS3Key = $rawFileS3Key;

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

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): self
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getTechnicalAckSentAt(): ?\DateTimeImmutable
    {
        return $this->technicalAckSentAt;
    }

    public function setTechnicalAckSentAt(?\DateTimeImmutable $v): self
    {
        $this->technicalAckSentAt = $v;

        return $this;
    }

    public function getContestReason(): ?string
    {
        return $this->contestReason;
    }

    public function setContestReason(?string $contestReason): self
    {
        $this->contestReason = $contestReason;

        return $this;
    }

    public function getContestDescription(): ?string
    {
        return $this->contestDescription;
    }

    public function setContestDescription(?string $contestDescription): self
    {
        $this->contestDescription = $contestDescription;

        return $this;
    }

    /** @return Collection<int, ReceivedInvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(ReceivedInvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setReceivedInvoice($this);
        }

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }
}
