<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ExportStatus;
use App\Entity\Enum\ExportType;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\ExportJobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Tâche d'export asynchrone (FEC, CSV, archive XML). Générée par un worker
 * Messenger, le fichier est déposé sur S3 avec une URL signée temporaire.
 * Le hash garantit l'intégrité (notamment pour le FEC, contrôle fiscal).
 */
#[ORM\Entity(repositoryClass: ExportJobRepository::class)]
#[ORM\Table(name: 'export_jobs')]
#[ORM\Index(name: 'idx_export_tenant_status', columns: ['tenant_id', 'status'])]
class ExportJob
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', enumType: ExportType::class)]
    private ExportType $type;

    #[ORM\Column(type: 'string', enumType: ExportStatus::class, options: ['default' => 'PENDING'])]
    private ExportStatus $status = ExportStatus::PENDING;

    /** Paramètres de l'export (période, filtres, format). */
    #[ORM\Column(type: 'json')]
    private array $params = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $s3Key = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rowCount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'generated_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $generatedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** Expiration du lien de téléchargement (purge S3). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): ExportType
    {
        return $this->type;
    }

    public function setType(ExportType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ExportStatus
    {
        return $this->status;
    }

    public function setStatus(ExportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getS3Key(): ?string
    {
        return $this->s3Key;
    }

    public function setS3Key(?string $s3Key): self
    {
        $this->s3Key = $s3Key;

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

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getRowCount(): ?int
    {
        return $this->rowCount;
    }

    public function setRowCount(?int $rowCount): self
    {
        $this->rowCount = $rowCount;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getGeneratedBy(): ?User
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(?User $generatedBy): self
    {
        $this->generatedBy = $generatedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
