<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\WebhookEndpointRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Endpoint HTTP du client pour recevoir les événements de l'application
 * (facture payée, transmission acceptée…). Les payloads sont signés HMAC
 * avec secretHash. Désactivé automatiquement après trop d'échecs.
 */
#[ORM\Entity(repositoryClass: WebhookEndpointRepository::class)]
#[ORM\Table(name: 'webhook_endpoints')]
#[ORM\Index(name: 'idx_webhook_endpoint_tenant', columns: ['tenant_id'])]
class WebhookEndpoint
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 500)]
    private string $url;

    /** Événements souscrits, ex : ["invoice.paid","transmission.acknowledged"]. */
    #[ORM\Column(type: 'json')]
    private array $events = [];

    /** Hash du secret de signature HMAC. */
    #[ORM\Column(length: 64)]
    private string $secretHash;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $failureCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    /** @var Collection<int, WebhookDelivery> */
    #[ORM\OneToMany(targetEntity: WebhookDelivery::class, mappedBy: 'endpoint', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $deliveries;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->deliveries = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function setEvents(array $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function setSecretHash(string $secretHash): self
    {
        $this->secretHash = $secretHash;

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

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function setFailureCount(int $failureCount): self
    {
        $this->failureCount = $failureCount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function setLastSuccessAt(?\DateTimeImmutable $lastSuccessAt): self
    {
        $this->lastSuccessAt = $lastSuccessAt;

        return $this;
    }

    /** @return Collection<int, WebhookDelivery> */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(WebhookDelivery $delivery): self
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
            $delivery->setEndpoint($this);
        }

        return $this;
    }
}
