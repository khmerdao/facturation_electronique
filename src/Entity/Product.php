<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ProductType;
use App\Entity\Enum\VatExemptionReason;
use App\Entity\Trait\TenantAwareTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Article du catalogue (produit ou service). Référence unique par tenant.
 * Les valeurs (prix, taux TVA) sont COPIÉES dans InvoiceLine au moment de la
 * sélection — modifier un produit n'affecte jamais les factures existantes.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_product_reference_tenant', columns: ['tenant_id', 'reference'])]
#[ORM\Index(name: 'idx_product_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_product_tva', columns: ['tva_rate'])]
class Product
{
    use TenantAwareTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', enumType: ProductType::class)]
    private ProductType $type = ProductType::SERVICE;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $reference;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4)]
    #[Assert\PositiveOrZero]
    private string $unitPrice = '0.0000';

    #[ORM\Column(length: 20)]
    private string $unit = 'U';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $tvaRate = '20.00';

    #[ORM\Column(type: 'string', enumType: VatExemptionReason::class, nullable: true)]
    private ?VatExemptionReason $tvaExemptionReason = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $accountingCode = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $supplierReference = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, nullable: true)]
    private ?string $minPrice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, ProductPriceHistory> */
    #[ORM\OneToMany(targetEntity: ProductPriceHistory::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['changedAt' => 'DESC'])]
    private Collection $priceHistory;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->priceHistory = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): ProductType
    {
        return $this->type;
    }

    public function setType(ProductType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

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

    public function setTvaExemptionReason(?VatExemptionReason $tvaExemptionReason): self
    {
        $this->tvaExemptionReason = $tvaExemptionReason;

        return $this;
    }

    public function getAccountingCode(): ?string
    {
        return $this->accountingCode;
    }

    public function setAccountingCode(?string $accountingCode): self
    {
        $this->accountingCode = $accountingCode;

        return $this;
    }

    public function getSupplierReference(): ?string
    {
        return $this->supplierReference;
    }

    public function setSupplierReference(?string $supplierReference): self
    {
        $this->supplierReference = $supplierReference;

        return $this;
    }

    public function getMinPrice(): ?string
    {
        return $this->minPrice;
    }

    public function setMinPrice(?string $minPrice): self
    {
        $this->minPrice = $minPrice;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    /** @return Collection<int, ProductPriceHistory> */
    public function getPriceHistory(): Collection
    {
        return $this->priceHistory;
    }

    public function addPriceHistory(ProductPriceHistory $entry): self
    {
        if (!$this->priceHistory->contains($entry)) {
            $this->priceHistory->add($entry);
            $entry->setProduct($this);
        }

        return $this;
    }
}
