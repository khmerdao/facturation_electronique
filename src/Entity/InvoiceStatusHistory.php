<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\InvoiceStatus;
use App\Repository\InvoiceStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Trace immuable de chaque transition de statut d'une facture.
 * INSERT only — alimente la timeline du cycle de vie DGFiP.
 */
#[ORM\Entity(repositoryClass: InvoiceStatusHistoryRepository::class)]
#[ORM\Table(name: 'invoice_status_history')]
#[ORM\Index(name: 'idx_status_history_invoice', columns: ['invoice_id'])]
class InvoiceStatusHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class, nullable: true)]
    private ?InvoiceStatus $fromStatus = null;

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class)]
    private InvoiceStatus $toStatus;

    /** Null = transition système (transmission PDP, accusé…). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

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

    public function getFromStatus(): ?InvoiceStatus
    {
        return $this->fromStatus;
    }

    public function setFromStatus(?InvoiceStatus $fromStatus): self
    {
        $this->fromStatus = $fromStatus;

        return $this;
    }

    public function getToStatus(): InvoiceStatus
    {
        return $this->toStatus;
    }

    public function setToStatus(InvoiceStatus $toStatus): self
    {
        $this->toStatus = $toStatus;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
