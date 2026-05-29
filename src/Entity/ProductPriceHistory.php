<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\ProductPriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Historique des modifications de prix d'un produit.
 * Alimenté automatiquement par ProductPriceHistoryListener (Doctrine preUpdate).
 */
#[ORM\Entity(repositoryClass: ProductPriceHistoryRepository::class)]
#[ORM\Table(name: 'product_price_history')]
#[ORM\Index(name: 'idx_price_history_product', columns: ['product_id'])]
class ProductPriceHistory
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'priceHistory')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4)]
    private string $oldPrice;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 4)]
    private string $newPrice;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getOldPrice(): string
    {
        return $this->oldPrice;
    }

    public function setOldPrice(string $oldPrice): self
    {
        $this->oldPrice = $oldPrice;

        return $this;
    }

    public function getNewPrice(): string
    {
        return $this->newPrice;
    }

    public function setNewPrice(string $newPrice): self
    {
        $this->newPrice = $newPrice;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): self
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
