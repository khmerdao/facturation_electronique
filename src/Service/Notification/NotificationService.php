<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\NotificationSeverity;
use App\Messenger\Message\SendNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Crée des notifications en base et les dispatche vers Messenger
 * pour envoi email si les préférences de l'utilisateur le permettent.
 */
final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * Crée une notification pour un utilisateur spécifique ou toute l'équipe.
     *
     * @param array<string, mixed>|null $payload Données supplémentaires (ex: invoice_id)
     */
    public function notify(
        Tenant $tenant,
        string $type,
        string $title,
        string $description,
        NotificationSeverity $severity = NotificationSeverity::INFO,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?User $user = null,
        ?array $payload = null,
    ): Notification {
        $notification = new Notification();
        $notification->setTenant($tenant);
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setSeverity($severity);
        $notification->setTitle($title);
        $notification->setDescription($description);
        $notification->setActionUrl($actionUrl);
        $notification->setActionLabel($actionLabel);
        $notification->setPayload($payload);

        $this->em->persist($notification);
        $this->em->flush();

        // Dispatcher en async pour l'envoi email
        $this->bus->dispatch(new SendNotificationMessage((string) $notification->getId()));

        return $notification;
    }

    /**
     * Raccourci pour une notification d'alerte critique (rouge).
     */
    public function alert(Tenant $tenant, string $type, string $title, string $description, ?User $user = null): Notification
    {
        return $this->notify($tenant, $type, $title, $description, NotificationSeverity::DANGER, user: $user);
    }

    /**
     * Raccourci pour une notification d'avertissement (orange).
     */
    public function warning(Tenant $tenant, string $type, string $title, string $description, ?User $user = null): Notification
    {
        return $this->notify($tenant, $type, $title, $description, NotificationSeverity::WARNING, user: $user);
    }

    /**
     * Raccourci pour une notification informative (bleue).
     */
    public function info(Tenant $tenant, string $type, string $title, string $description, ?User $user = null): Notification
    {
        return $this->notify($tenant, $type, $title, $description, NotificationSeverity::INFO, user: $user);
    }

    /**
     * Raccourci pour une notification de succès (verte).
     */
    public function success(Tenant $tenant, string $type, string $title, string $description, ?User $user = null): Notification
    {
        return $this->notify($tenant, $type, $title, $description, NotificationSeverity::SUCCESS, user: $user);
    }
}
