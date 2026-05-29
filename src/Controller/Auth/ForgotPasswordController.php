<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mot de passe oublié et réinitialisation.
 * Utilise EmailVerificationToken (durée 24h) comme token de reset.
 */
final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $mailerFromAddress,
        private readonly string $appUrl,
    ) {}

    // ── Mot de passe oublié ────────────────────────────────────────────────

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        $sent = false;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $user  = $this->userRepository->findByEmail($email);

            // On envoie le mail même si le compte n'existe pas (anti-enumération)
            if ($user) {
                $this->sendResetEmail($user);
            }

            $sent = true;
        }

        return $this->render('auth/forgot_password.html.twig', [
            'sent' => $sent,
        ]);
    }

    // ── Réinitialisation du mot de passe ───────────────────────────────────

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request): Response
    {
        $tokenEntity = $this->em->getRepository(EmailVerificationToken::class)
            ->findOneBy(['token' => $token]);

        if (!$tokenEntity || $tokenEntity->getUsedAt() || $tokenEntity->getExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce lien est invalide ou a expiré.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('password_confirm', '');

            if (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                $user = $tokenEntity->getUser();
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $tokenEntity->setUsedAt(new \DateTimeImmutable());

                $this->em->flush();

                $this->addFlash('success', 'Mot de passe modifié. Connectez-vous !');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'error' => $error,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function sendResetEmail(User $user): void
    {
        $token = new EmailVerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));


        $this->em->persist($token);
        $this->em->flush();

        $resetUrl = $this->appUrl . $this->generateUrl(
            'app_reset_password',
            ['token' => $token->getToken()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html(sprintf(
                '<p>Bonjour %s,</p>
                 <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe (valable 24h) :</p>
                 <p><a href="%s">%s</a></p>
                 <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>',
                htmlspecialchars($user->getFirstName() ?? $user->getEmail()),
                $resetUrl,
                $resetUrl,
            ));

        $this->mailer->send($email);
    }
}
