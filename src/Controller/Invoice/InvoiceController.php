<?php

declare(strict_types=1);

namespace App\Controller\Invoice;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Factures émises — liste et gestion.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/invoices', name: 'app_invoices_')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('invoice/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
