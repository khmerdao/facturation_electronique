<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\PdpWebhookLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal des webhooks entrants de la PDP (statuts de cycle de vie, factures
 * reçues). eventId assure l'idempotence : un même événement n'est traité
 * qu'une fois. Conserve le payload brut pour rejeu et audit.
 */
#[ORM\Entity(repositoryClass: PdpWebhookLogRepository::class)]
#[ORM\Table(name: 'pdp_webhook_logs')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_event', columns: ['tenant_id', 'event_id'])]
#[ORM\Index(name: 'idx_webhook_log_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_webhook_log_type', columns: ['event_type'])]
class PdpWebhookLog
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Identifiant unique de l'événement côté PDP (idempotence). */
    #[ORM\Column(length: 191)]
    private string $eventId;

    #[ORM\Column(length: 80)]
    private string $eventType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $processed = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $processingError = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $signatureHash = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): self
    {
        $this->processed = $processed;

        return $this;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
    }

    public function setProcessingError(?string $processingError): self
    {
        $this->processingError = $processingError;

        return $this;
    }

    public function getSignatureHash(): ?string
    {
        return $this->signatureHash;
    }

    public function setSignatureHash(?string $signatureHash): self
    {
        $this->signatureHash = $signatureHash;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;

        return $this;
    }
}
