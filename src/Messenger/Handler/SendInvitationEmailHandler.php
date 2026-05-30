<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\SendInvitationEmailMessage;
use App\Repository\TenantInvitationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendInvitationEmailHandler
{
    public function __construct(
        private readonly TenantInvitationRepository $invitationRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
        private readonly string $appUrl,
    ) {}

    public function __invoke(SendInvitationEmailMessage $message): void
    {
        $invitation = $this->invitationRepository->find($message->getInvitationId());
        if (!$invitation || $invitation->getExpiresAt() < new \DateTimeImmutable()) return;

        $body = $this->twig->render('emails/invitation.html.twig', [
            'tenant'  => $invitation->getTenant(),
            'role'    => $invitation->getRole()->label(),
            'token'   => $invitation->getToken(),
            'app_url' => $this->appUrl,
        ]);

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to($invitation->getEmail())
                ->subject('Invitation à rejoindre ' . $invitation->getTenant()->getName() . ' sur FacturePro')
                ->html($body)
        );
    }
}
