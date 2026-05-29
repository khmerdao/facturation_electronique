<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Trait\TenantAwareTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Facture émise. Couvre aussi les avoirs (type CREDIT_NOTE) et proformas.
 * Immuable dès le statut VALIDATED (numéro alloué, archivage WORM S3).
 * Champ version pour optimistic lock (édition concurrente des brouillons).
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\Index(name: 'idx_invoice_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_invoice_tenant_issue', columns: ['tenant_id', 'issue_date'])]
#[ORM\Index(name: 'idx_invoice_number', columns: ['number'])]
#[ORM\Index(name: 'idx_invoice_contact', columns: ['contact_id'])]
class Invoice
{
    use TenantAwareTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Null tant que le statut est DRAFT (alloué à la validation). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class, options: ['default' => 'DRAFT'])]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\Column(type: 'string', enumType: InvoiceType::class, options: ['default' => 'INVOICE'])]
    private InvoiceType $type = InvoiceType::INVOICE;

    #[ORM\Column(type: 'string', enumType: InvoiceFormat::class, options: ['default' => 'FACTURX'])]
    private InvoiceFormat $format = InvoiceFormat::FACTURX;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: 'contact_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Contact $contact = null;

    /** Séquence utilisée pour la numérotation (factures ou avoirs). */
    #[ORM\ManyToOne(targetEntity: InvoiceSequence::class)]
    #[ORM\JoinColumn(name: 'sequence_id', nullable: true, onDelete: 'SET NULL')]
    private ?InvoiceSequence $sequence = null;

    /** Template PDF appliqué. */
    #[ORM\ManyToOne(targetEntity: InvoiceTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: true, onDelete: 'SET NULL')]
    private ?InvoiceTemplate $template = null;

    /** Si c'est un avoir : la facture d'origine (BillingReference). */
    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'credit_note_for_id', nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $creditNoteFor = null;

    // Snapshot des données client au moment de l'émission (immuabilité)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientNameSnapshot = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $clientSiretSnapshot = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $clientPdpIdentifier = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalTva = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountPaid = '0.00';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $clientReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $clientNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;

    // Archivage & intégrité (piste d'audit fiable)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfS3Key = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $xmlS3Key = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** Optimistic lock pour l'édition concurrente des brouillons. */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, InvoiceStatusHistory> */
    #[ORM\OneToMany(targetEntity: InvoiceStatusHistory::class, mappedBy: 'invoice', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $statusHistory;

    /** @var Collection<int, PdpTransmission> */
    #[ORM\OneToMany(targetEntity: PdpTransmission::class, mappedBy: 'invoice', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $transmissions;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice')]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $payments;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->issueDate = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->transmissions = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function setType(InvoiceType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFormat(): InvoiceFormat
    {
        return $this->format;
    }

    public function setFormat(InvoiceFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getSequence(): ?InvoiceSequence
    {
        return $this->sequence;
    }

    public function setSequence(?InvoiceSequence $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getTemplate(): ?InvoiceTemplate
    {
        return $this->template;
    }

    public function setTemplate(?InvoiceTemplate $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getCreditNoteFor(): ?Invoice
    {
        return $this->creditNoteFor;
    }

    public function setCreditNoteFor(?Invoice $creditNoteFor): self
    {
        $this->creditNoteFor = $creditNoteFor;

        return $this;
    }

    public function getClientNameSnapshot(): ?string
    {
        return $this->clientNameSnapshot;
    }

    public function setClientNameSnapshot(?string $v): self
    {
        $this->clientNameSnapshot = $v;

        return $this;
    }

    public function getClientSiretSnapshot(): ?string
    {
        return $this->clientSiretSnapshot;
    }

    public function setClientSiretSnapshot(?string $v): self
    {
        $this->clientSiretSnapshot = $v;

        return $this;
    }

    public function getClientPdpIdentifier(): ?string
    {
        return $this->clientPdpIdentifier;
    }

    public function setClientPdpIdentifier(?string $v): self
    {
        $this->clientPdpIdentifier = $v;

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

    public function getTotalHt(): string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): self
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getTotalTva(): string
    {
        return $this->totalTva;
    }

    public function setTotalTva(string $totalTva): self
    {
        $this->totalTva = $totalTva;

        return $this;
    }

    public function getTotalTtc(): string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): self
    {
        $this->totalTtc = $totalTtc;

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

    public function getRemainingDue(): string
    {
        return bcsub($this->totalTtc, $this->amountPaid, 2);
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): self
    {
        $this->issueDate = $issueDate;

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

    public function getClientReference(): ?string
    {
        return $this->clientReference;
    }

    public function setClientReference(?string $clientReference): self
    {
        $this->clientReference = $clientReference;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getClientNotes(): ?string
    {
        return $this->clientNotes;
    }

    public function setClientNotes(?string $clientNotes): self
    {
        $this->clientNotes = $clientNotes;

        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): self
    {
        $this->internalNotes = $internalNotes;

        return $this;
    }

    public function getPdfS3Key(): ?string
    {
        return $this->pdfS3Key;
    }

    public function setPdfS3Key(?string $pdfS3Key): self
    {
        $this->pdfS3Key = $pdfS3Key;

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

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): self
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): self
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, InvoiceStatusHistory> */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function addStatusHistory(InvoiceStatusHistory $entry): self
    {
        if (!$this->statusHistory->contains($entry)) {
            $this->statusHistory->add($entry);
            $entry->setInvoice($this);
        }

        return $this;
    }

    /** @return Collection<int, PdpTransmission> */
    public function getTransmissions(): Collection
    {
        return $this->transmissions;
    }

    public function addTransmission(PdpTransmission $transmission): self
    {
        if (!$this->transmissions->contains($transmission)) {
            $this->transmissions->add($transmission);
            $transmission->setInvoice($this);
        }

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }
}
