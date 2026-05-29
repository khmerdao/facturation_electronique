<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReceivedInvoiceLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Ligne d'une facture reçue, extraite du XML. */
#[ORM\Entity(repositoryClass: ReceivedInvoiceLineRepository::class)]
#[ORM\Table(name: 'received_invoice_lines')]
#[ORM\Index(name: 'idx_received_line_invoice', columns: ['received_invoice_id'])]
class ReceivedInvoiceLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ReceivedInvoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'received_invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?ReceivedInvoice $receivedInvoice = null;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, options: ['default' => '1.0000'])]
    private string $quantity = '1.0000';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, options: ['default' => '0.0000'])]
    private string $unitPrice = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '20.00'])]
    private string $tvaRate = '20.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountHt = '0.00';

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getTvaRate(): string
    {
        return $this->tvaRate;
    }

    public function setTvaRate(string $tvaRate): self
    {
        $this->tvaRate = $tvaRate;

        return $this;
    }

    public function getAmountHt(): string
    {
        return $this->amountHt;
    }

    public function setAmountHt(string $amountHt): self
    {
        $this->amountHt = $amountHt;

        return $this;
    }
}
