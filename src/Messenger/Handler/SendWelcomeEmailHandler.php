<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\SendWelcomeEmailMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class SendWelcomeEmailHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
        private readonly string $appUrl,
    ) {}

    public function __invoke(SendWelcomeEmailMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        if (!$user) return;

        $body = $this->twig->render('emails/welcome.html.twig', [
            'user'    => $user,
            'app_url' => $this->appUrl,
        ]);

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to($user->getEmail())
                ->subject('Bienvenue sur FacturePro — Démarrez votre configuration')
                ->html($body)
        );
    }
}
