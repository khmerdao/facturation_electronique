<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\InvoiceTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Modèle de facture PDF personnalisable. La configuration (zones visibles,
 * couleurs, polices, mentions) est stockée en JSON pour flexibilité.
 */
#[ORM\Entity(repositoryClass: InvoiceTemplateRepository::class)]
#[ORM\Table(name: 'invoice_templates')]
#[ORM\Index(name: 'idx_template_tenant', columns: ['tenant_id'])]
class InvoiceTemplate
{
    use TenantAwareTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    /** Clé du template de base : classique|moderne|compact|detaille */
    #[ORM\Column(length: 50)]
    private string $baseKey = 'classique';

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCustomized = false;

    /** Configuration complète du template (zones, style, mentions). */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewS3Key = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBaseKey(): string
    {
        return $this->baseKey;
    }

    public function setBaseKey(string $baseKey): self
    {
        $this->baseKey = $baseKey;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function isCustomized(): bool
    {
        return $this->isCustomized;
    }

    public function setIsCustomized(bool $isCustomized): self
    {
        $this->isCustomized = $isCustomized;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getPreviewS3Key(): ?string
    {
        return $this->previewS3Key;
    }

    public function setPreviewS3Key(?string $previewS3Key): self
    {
        $this->previewS3Key = $previewS3Key;

        return $this;
    }
}
