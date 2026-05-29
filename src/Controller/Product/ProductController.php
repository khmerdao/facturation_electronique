<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue produits et services.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/products', name: 'app_products_')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('product/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
