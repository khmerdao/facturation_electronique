<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use App\Entity\Enum\PdpMode;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configuration de connexion PDP/PPF, embarquée dans Tenant.
 * Les credentials sont chiffrés AES-256 (champ apiKeyEncrypted) par
 * PdpConfigEncryptor avant persistance — jamais stockés en clair.
 */
#[ORM\Embeddable]
class PdpConfig
{
    #[ORM\Column(type: 'string', length: 10, nullable: true, enumType: PdpMode::class)]
    private ?PdpMode $mode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $pdpName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $endpointUrl = null;

    /** Clé API chiffrée AES-256 (jamais en clair). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiKeyEncrypted = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $emitterId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $lastTestStatus = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $connectedAt = null;

    public function getMode(): ?PdpMode
    {
        return $this->mode;
    }

    public function setMode(?PdpMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getPdpName(): ?string
    {
        return $this->pdpName;
    }

    public function setPdpName(?string $pdpName): self
    {
        $this->pdpName = $pdpName;

        return $this;
    }

    public function getEndpointUrl(): ?string
    {
        return $this->endpointUrl;
    }

    public function setEndpointUrl(?string $endpointUrl): self
    {
        $this->endpointUrl = $endpointUrl;

        return $this;
    }

    public function getApiKeyEncrypted(): ?string
    {
        return $this->apiKeyEncrypted;
    }

    public function setApiKeyEncrypted(?string $apiKeyEncrypted): self
    {
        $this->apiKeyEncrypted = $apiKeyEncrypted;

        return $this;
    }

    public function getEmitterId(): ?string
    {
        return $this->emitterId;
    }

    public function setEmitterId(?string $emitterId): self
    {
        $this->emitterId = $emitterId;

        return $this;
    }

    public function getLastTestStatus(): ?string
    {
        return $this->lastTestStatus;
    }

    public function setLastTestStatus(?string $lastTestStatus): self
    {
        $this->lastTestStatus = $lastTestStatus;

        return $this;
    }

    public function getConnectedAt(): ?\DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function setConnectedAt(?\DateTimeImmutable $connectedAt): self
    {
        $this->connectedAt = $connectedAt;

        return $this;
    }

    public function isConfigured(): bool
    {
        return null !== $this->mode && null !== $this->emitterId;
    }
}
