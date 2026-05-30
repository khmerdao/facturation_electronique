<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\SendNotificationMessage;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Entity\Enum\EmailDigest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Envoie l'email de notification si l'utilisateur a activé les emails immédiats.
 */
#[AsMessageHandler]
final class SendNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromAddress,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendNotificationMessage $message): void
    {
        $notification = $this->notificationRepository->find($message->getNotificationId());

        if (!$notification) {
            return;
        }

        $user = $notification->getUser();
        if (!$user) {
            return; // Notification d'équipe — pas d'email individuel
        }

        // Vérifier les préférences de l'utilisateur
        $pref = $this->preferenceRepository->findOneBy([
            'user'             => $user,
            'notificationType' => $notification->getType(),
        ]);

        // Par défaut : emails immédiats activés
        if ($pref && !$pref->isEmailEnabled()) {
            return;
        }

        if ($pref && $pref->getEmailDigest() !== EmailDigest::IMMEDIATE) {
            return; // Sera envoyé dans le digest
        }

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject($notification->getTitle())
            ->html(sprintf(
                '<p>%s</p>%s<p><small>%s</small></p>',
                htmlspecialchars($notification->getTitle()),
                $notification->getDescription()
                    ? '<p>' . htmlspecialchars($notification->getDescription()) . '</p>'
                    : '',
                $notification->getActionUrl()
                    ? sprintf('<a href="%s">%s</a>', $notification->getActionUrl(), $notification->getActionLabel() ?? 'Voir')
                    : '',
            ));

        try {
            $this->mailer->send($email);
            $this->logger->info('notification.email.sent', [
                'notification_id' => $message->getNotificationId(),
                'user_email'      => $user->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('notification.email.failed', [
                'notification_id' => $message->getNotificationId(),
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
