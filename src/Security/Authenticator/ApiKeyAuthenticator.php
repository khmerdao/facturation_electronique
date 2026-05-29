<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use App\Repository\ApiKeyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentification par clé API (header X-Api-Key).
 * La clé en clair est hashée en SHA-256 puis comparée au hash stocké.
 * Utilisé sur le firewall "api" en complément du JWT.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const HEADER_NAME = 'X-Api-Key';

    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
    ) {}

    /**
     * Cet authentificateur est actif uniquement si l'en-tête X-Api-Key est présent.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $rawKey = $request->headers->get(self::HEADER_NAME, '');

        if (empty($rawKey)) {
            throw new CustomUserMessageAuthenticationException('Clé API manquante.');
        }

        // Hasher la clé reçue pour la comparer à celle stockée (SHA-256)
        $keyHash = hash('sha256', $rawKey);

        $apiKey = $this->apiKeyRepository->findByHash($keyHash);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Clé API invalide ou révoquée.');
        }

        // Mettre à jour la date de dernière utilisation (asynchrone)
        $this->apiKeyRepository->touchLastUsed($apiKey);

        return new SelfValidatingPassport(
            new UserBadge(
                $apiKey->getTenant()?->getId() . ':' . $apiKey->getCreatedBy()?->getId(),
                fn () => $apiKey->getCreatedBy(),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // null = laisser la requête continuer vers le controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey(), 'message' => 'Authentification requise.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
