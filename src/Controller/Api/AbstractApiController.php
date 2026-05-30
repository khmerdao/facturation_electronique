<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classe de base des controllers API REST.
 *
 * Fournit :
 *  - paginate()   : pagination standard (page/per_page depuis la query string)
 *  - success()    : réponse JSON 200/201 avec données
 *  - error()      : réponse Problem Details (RFC 7807)
 *  - serialize()  : sérialisation d'une entité en tableau (à surcharger par resource)
 */
abstract class AbstractApiController extends AbstractController
{
    public function __construct(
        protected readonly TenantContext $tenantContext,
    ) {}

    // ── Pagination ────────────────────────────────────────────────────────────

    protected function getPaginationParams(Request $request, int $defaultPerPage = 20): array
    {
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', $defaultPerPage)));

        return [
            'page'    => $page,
            'perPage' => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ];
    }

    /**
     * Réponse paginée avec headers X-Total-Count et Link (RFC 5988).
     */
    protected function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage,
        string $baseUrl,
    ): JsonResponse {
        $pages    = (int) ceil($total / $perPage);
        $response = $this->json([
            'data'       => $items,
            'pagination' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_pages' => $pages,
            ],
        ]);

        $response->headers->set('X-Total-Count', (string) $total);

        // Link header (RFC 5988)
        $links = [];
        if ($page < $pages) {
            $links[] = sprintf('<%s?page=%d&per_page=%d>; rel="next"', $baseUrl, $page + 1, $perPage);
            $links[] = sprintf('<%s?page=%d&per_page=%d>; rel="last"', $baseUrl, $pages, $perPage);
        }
        if ($page > 1) {
            $links[] = sprintf('<%s?page=%d&per_page=%d>; rel="prev"', $baseUrl, $page - 1, $perPage);
            $links[] = sprintf('<%s?page=%d&per_page=%d>; rel="first"', $baseUrl, 1, $perPage);
        }
        if ($links) {
            $response->headers->set('Link', implode(', ', $links));
        }

        return $response;
    }

    // ── Réponses standard ─────────────────────────────────────────────────────

    protected function success(array|object $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json($data, $status);
    }

    protected function created(array|object $data): JsonResponse
    {
        return $this->json($data, Response::HTTP_CREATED);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Erreur RFC 7807 Problem Details.
     */
    protected function problem(
        string $detail,
        int $status = Response::HTTP_BAD_REQUEST,
        string $title = 'Bad Request',
        array $extra = [],
    ): JsonResponse {
        return $this->json(
            array_merge(['title' => $title, 'status' => $status, 'detail' => $detail], $extra),
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    protected function notFound(string $detail = 'Ressource introuvable.'): JsonResponse
    {
        return $this->problem($detail, Response::HTTP_NOT_FOUND, 'Not Found');
    }

    protected function forbidden(string $detail = 'Accès refusé.'): JsonResponse
    {
        return $this->problem($detail, Response::HTTP_FORBIDDEN, 'Forbidden');
    }

    protected function unprocessable(string $detail, array $violations = []): JsonResponse
    {
        return $this->problem(
            $detail,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Validation Failed',
            ['violations' => $violations],
        );
    }

    // ── Guard tenant ──────────────────────────────────────────────────────────

    /**
     * Vérifie que l'entité appartient au tenant courant.
     * Retourne true si OK, false sinon (l'appelant doit retourner notFound()).
     */
    protected function belongsToCurrentTenant(object $entity): bool
    {
        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            return false;
        }

        if (!method_exists($entity, 'getTenant')) {
            return true;
        }

        return (string) $entity->getTenant()?->getId() === (string) $tenant->getId();
    }
}
