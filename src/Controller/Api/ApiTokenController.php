<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Génère un token JWT depuis les credentials email + password.
 *
 * POST /api/auth/token
 * Body JSON : { "email": "...", "password": "...", "tenant_id": "..." }
 *
 * Le tenant_id est requis quand l'utilisateur appartient à plusieurs organisations.
 * Retourne : { "token": "eyJ...", "expires_at": 1234567890 }
 */
final class ApiTokenController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/api/auth/token', name: 'api_auth_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true) ?? [];
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $tenantId = $data['tenant_id'] ?? null;

        if (!$email || !$password) {
            return $this->apiError('Les champs email et password sont requis.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->apiError('Identifiants incorrects.', Response::HTTP_UNAUTHORIZED);
        }

        // Résoudre le tenant
        $memberships = $this->membershipRepository->findTenantsForUser($user);

        if (empty($memberships)) {
            return $this->apiError('Aucune organisation associée à ce compte.', Response::HTTP_FORBIDDEN);
        }

        $membership = null;

        if ($tenantId) {
            foreach ($memberships as $m) {
                if ((string) $m->getTenant()->getId() === $tenantId) {
                    $membership = $m;
                    break;
                }
            }
            if (!$membership) {
                return $this->apiError('Organisation non trouvée ou accès refusé.', Response::HTTP_FORBIDDEN);
            }
        } elseif (count($memberships) === 1) {
            $membership = $memberships[0];
        } else {
            // Plusieurs organisations — renvoyer la liste pour que le client choisisse
            return $this->json([
                'error'       => 'tenant_selection_required',
                'message'     => 'Ce compte appartient à plusieurs organisations. Précisez tenant_id.',
                'tenants'     => array_map(fn($m) => [
                    'id'   => (string) $m->getTenant()->getId(),
                    'name' => $m->getTenant()->getName(),
                    'role' => $m->getRole()->value,
                ], $memberships),
            ], Response::HTTP_MULTI_STATUS);
        }

        $tenant = $membership->getTenant();
        $ttl    = (int) ($_ENV['JWT_TTL'] ?? 3600);

        // Générer le token avec claims supplémentaires
        $token = $this->jwtManager->createFromPayload($user, [
            'tenant_id'   => (string) $tenant->getId(),
            'tenant_name' => $tenant->getName(),
            'role'        => $membership->getRole()->value,
        ]);

        return $this->json([
            'token'      => $token,
            'expires_at' => time() + $ttl,
            'tenant'     => [
                'id'   => (string) $tenant->getId(),
                'name' => $tenant->getName(),
            ],
            'user' => [
                'id'    => (string) $user->getId(),
                'email' => $user->getEmail(),
                'role'  => $membership->getRole()->value,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function apiError(string $message, int $status): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }
}
