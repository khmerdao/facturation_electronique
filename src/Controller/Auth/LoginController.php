<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur de connexion / déconnexion.
 * La logique d'authentification est gérée par AppAuthenticator.
 */
final class LoginController extends AbstractController
{
    /**
     * Page de connexion principale.
     * Affiche les erreurs de connexion et le dernier email saisi.
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authUtils): Response
    {
        // Redirige si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Déconnexion — interceptée par le firewall Symfony.
     * Cette méthode n'est jamais exécutée directement.
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the firewall.');
    }

    /**
     * Formulaire de saisie du code 2FA (TOTP).
     */
    #[Route('/2fa', name: 'app_2fa_login', methods: ['GET', 'POST'])]
    public function twoFactor(): Response
    {
        return $this->render('auth/2fa.html.twig');
    }
}
