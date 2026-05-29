<?php

declare(strict_types=1);

namespace App\Controller\Tax;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord TVA et exports comptables.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/tax', name: 'app_tax_')]
final class TaxController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('tax/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
