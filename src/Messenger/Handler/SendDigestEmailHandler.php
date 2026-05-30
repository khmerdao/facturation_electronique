<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Entity\Enum\EmailDigest;
use App\Messenger\Message\SendDigestEmailMessage;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendDigestEmailHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantRepository $tenantRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
    ) {}

    public function __invoke(SendDigestEmailMessage $message): void
    {
        $user   = $this->userRepository->find($message->getUserId());
        $tenant = $this->tenantRepository->find($message->getTenantId());
        if (!$user || !$tenant) return;

        $since = (new \DateTimeImmutable())->modify('-1 day');
        $notifications = $this->notificationRepository->findForDigest(
            $user,
            $tenant,
            EmailDigest::DAILY->value,
            $since,
        );

        if (empty($notifications)) return;

        $body = $this->twig->render('emails/digest.html.twig', [
            'user'          => $user,
            'notifications' => $notifications,
            'period'        => 'du ' . $since->format('d/m/Y'),
            'app_url'       => '',
        ]);

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to($user->getEmail())
                ->subject('Résumé FacturePro — ' . (new \DateTimeImmutable())->format('d/m/Y'))
                ->html($body)
        );
    }
}
