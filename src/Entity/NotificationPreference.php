<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\EmailDigest;
use App\Entity\Trait\TenantAwareTrait;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Préférence de notification d'un utilisateur pour un type donné.
 * Un enregistrement par (user, type). L'absence = valeurs par défaut.
 */
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notification_preferences')]
#[ORM\UniqueConstraint(name: 'uniq_pref_user_type', columns: ['user_id', 'notification_type'])]
#[ORM\Index(name: 'idx_pref_user', columns: ['user_id'])]
class NotificationPreference
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 60)]
    private string $notificationType;

    #[ORM\Column(options: ['default' => true])]
    private bool $inAppEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $emailEnabled = true;

    #[ORM\Column(type: 'string', enumType: EmailDigest::class, options: ['default' => 'IMMEDIATE'])]
    private EmailDigest $emailDigest = EmailDigest::IMMEDIATE;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getNotificationType(): string
    {
        return $this->notificationType;
    }

    public function setNotificationType(string $notificationType): self
    {
        $this->notificationType = $notificationType;

        return $this;
    }

    public function isInAppEnabled(): bool
    {
        return $this->inAppEnabled;
    }

    public function setInAppEnabled(bool $inAppEnabled): self
    {
        $this->inAppEnabled = $inAppEnabled;

        return $this;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): self
    {
        $this->emailEnabled = $emailEnabled;

        return $this;
    }

    public function getEmailDigest(): EmailDigest
    {
        return $this->emailDigest;
    }

    public function setEmailDigest(EmailDigest $emailDigest): self
    {
        $this->emailDigest = $emailDigest;

        return $this;
    }
}
