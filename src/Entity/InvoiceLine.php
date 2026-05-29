<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\VatExemptionReason;
use App\Repository\InvoiceLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne de facture. Les valeurs (prix, TVA) sont une COPIE figée du produit
 * au moment de la création — garantit l'immuabilité des factures émises.
 * Le lien vers Product est conservé pour les statistiques d'utilisation mais
 * n'est jamais utilisé pour recalculer les montants.
 */
#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
#[ORM\Table(name: 'invoice_lines')]
#[ORM\Index(name: 'idx_invoice_line_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_invoice_line_product', columns: ['product_id'])]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    /** Référence au produit catalogue (nullable, conservée pour stats). */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    /** Ligne de type commentaire/section (sans montant) si true. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isComment = false;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, options: ['default' => '1.0000'])]
    private string $quantity = '1.0000';

    #[ORM\Column(length: 20, options: ['default' => 'U'])]
    private string $unit = 'U';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, options: ['default' => '0.0000'])]
    private string $unitPrice = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '0.00'])]
    private string $discount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '20.00'])]
    private string $tvaRate = '20.00';

    #[ORM\Column(type: 'string', enumType: VatExemptionReason::class, nullable: true)]
    private ?VatExemptionReason $tvaExemptionReason = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountTva = '0.00';

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isComment(): bool
    {
        return $this->isComment;
    }

    public function setIsComment(bool $isComment): self
    {
        $this->isComment = $isComment;

        return $this;
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

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

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

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): self
    {
        $this->discount = $discount;

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

    public function getTvaExemptionReason(): ?VatExemptionReason
    {
        return $this->tvaExemptionReason;
    }

    public function setTvaExemptionReason(?VatExemptionReason $reason): self
    {
        $this->tvaExemptionReason = $reason;

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

    public function getAmountTva(): string
    {
        return $this->amountTva;
    }

    public function setAmountTva(string $amountTva): self
    {
        $this->amountTva = $amountTva;

        return $this;
    }
}
