<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Security\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST — Catalogue produits et services.
 *
 * GET /api/products      — liste paginée
 * GET /api/products/{id} — détail
 */
#[Route('/api/products', name: 'api_products_')]
final class ApiProductController extends AbstractApiController
{
    public function __construct(
        TenantContext $tenantContext,
        private readonly ProductRepository $productRepository,
    ) {
        parent::__construct($tenantContext);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant  = $this->tenantContext->requireTenant();
        $params  = $this->getPaginationParams($request, 50);
        $q       = $request->query->get('q');

        $products = $q
            ? $this->productRepository->search($tenant, $q, 100)
            : $this->productRepository->findAllActive($tenant);

        $items = array_slice($products, $params['offset'], $params['perPage']);

        return $this->paginated(
            array_map($this->serialize(...), $items),
            count($products),
            $params['page'],
            $params['perPage'],
            '/api/products',
        );
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Product $product): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($product)) {
            return $this->notFound();
        }

        return $this->success($this->serialize($product));
    }

    private function serialize(Product $p): array
    {
        return [
            'id'             => (string) $p->getId(),
            'reference'      => $p->getReference(),
            'label'          => $p->getLabel(),
            'description'    => $p->getDescription(),
            'type'           => $p->getType()->value,
            'unit_price'     => $p->getUnitPrice(),
            'unit'           => $p->getUnit(),
            'tva_rate'       => $p->getTvaRate(),
            'accounting_code' => $p->getAccountingCode(),
            'active'         => $p->isActive(),
            'created_at'     => $p->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
