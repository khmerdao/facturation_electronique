<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST — Profil de l'utilisateur connecté.
 *
 * GET /api/me — retourne l'utilisateur et le tenant courant
 */
#[Route('/api/me', name: 'api_me_')]
final class ApiMeController extends AbstractApiController
{
    public function __construct(TenantContext $tenantContext)
    {
        parent::__construct($tenantContext);
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user       = $this->getUser();
        $tenant     = $this->tenantContext->getTenant();
        $membership = $this->tenantContext->getMembership();

        return $this->success([
            'user' => [
                'id'         => (string) $user->getId(),
                'email'      => $user->getEmail(),
                'first_name' => $user->getFirstName(),
                'last_name'  => $user->getLastName(),
            ],
            'tenant' => $tenant ? [
                'id'     => (string) $tenant->getId(),
                'name'   => $tenant->getName(),
                'slug'   => $tenant->getSlug(),
                'plan'   => $tenant->getPlan()->value,
                'siret'  => $tenant->getSiret(),
                'tva_intra' => $tenant->getTvaIntra(),
            ] : null,
            'role' => $membership?->getRole()->value,
        ]);
    }
}
