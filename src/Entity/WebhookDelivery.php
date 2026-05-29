<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\WebhookDeliveryStatus;
use App\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Tentative de livraison d'un événement vers un WebhookEndpoint.
 * Conserve le payload et le code HTTP de réponse pour le rejeu et le debug.
 */
#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'webhook_deliveries')]
#[ORM\Index(name: 'idx_delivery_endpoint', columns: ['endpoint_id'])]
#[ORM\Index(name: 'idx_delivery_status', columns: ['status'])]
class WebhookDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: WebhookEndpoint::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'endpoint_id', nullable: false, onDelete: 'CASCADE')]
    private ?WebhookEndpoint $endpoint = null;

    #[ORM\Column(length: 80)]
    private string $eventType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'string', enumType: WebhookDeliveryStatus::class, options: ['default' => 'PENDING'])]
    private WebhookDeliveryStatus $status = WebhookDeliveryStatus::PENDING;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEndpoint(): ?WebhookEndpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?WebhookEndpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

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

    public function getStatus(): WebhookDeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(WebhookDeliveryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): self
    {
        $this->responseBody = $responseBody;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?\DateTimeImmutable $nextRetryAt): self
    {
        $this->nextRetryAt = $nextRetryAt;

        return $this;
    }
}
