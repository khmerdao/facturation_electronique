<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Authentificateur principal (formulaire login email + mot de passe).
 * Gère le 2FA via scheb/2fa-bundle (le bundle intercepte après onAuthenticationSuccess).
 */
final class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepository,
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function (string $identifier): User {
                $user = $this->userRepository->findByEmail($identifier);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Identifiants incorrects.');
                }

                if (!$user->isEmailVerified()) {
                    throw new CustomUserMessageAuthenticationException(
                        'Veuillez vérifier votre adresse email avant de vous connecter.'
                    );
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Mettre à jour la date de dernière connexion
        /** @var User $user */
        $user = $token->getUser();
        $this->userRepository->updateLastLogin($user);

        // Retourner null = laisser le 2FA prendre la main si activé
        // (scheb/2fa-bundle intercepte et redirige vers /2fa si TOTP activé)

        // Rediriger vers la page d'origine (si tentative d'accès direct à une URL)
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Vérifier si l'utilisateur a plusieurs tenants → sélecteur
        // (géré par TenantFilterSubscriber sur la prochaine requête)
        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->router->generate(self::LOGIN_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate(self::LOGIN_ROUTE);
    }
}
