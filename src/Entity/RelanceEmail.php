<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\RelanceEmailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Relance de paiement envoyée pour une facture émise impayée.
 * Niveau croissant (1 = rappel courtois, 2 = relance ferme, 3 = mise en
 * demeure avec indemnité forfaitaire 40€ + intérêts de retard).
 */
#[ORM\Entity(repositoryClass: RelanceEmailRepository::class)]
#[ORM\Table(name: 'relance_emails')]
#[ORM\Index(name: 'idx_relance_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_relance_tenant', columns: ['tenant_id'])]
class RelanceEmail
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(length: 255)]
    private string $recipientEmail;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(options: ['default' => false])]
    private bool $includesLateFees = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sent_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $sentBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->sentAt = new \DateTimeImmutable();
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

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): self
    {
        $this->recipientEmail = $recipientEmail;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function includesLateFees(): bool
    {
        return $this->includesLateFees;
    }

    public function setIncludesLateFees(bool $includesLateFees): self
    {
        $this->includesLateFees = $includesLateFees;

        return $this;
    }

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }

    public function setSentBy(?User $sentBy): self
    {
        $this->sentBy = $sentBy;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTimeImmutable $openedAt): self
    {
        $this->openedAt = $openedAt;

        return $this;
    }
}
