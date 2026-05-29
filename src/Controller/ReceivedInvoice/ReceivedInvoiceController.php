<?php

declare(strict_types=1);

namespace App\Controller\ReceivedInvoice;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Factures reçues via PDP/PPF.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/received-invoices', name: 'app_received_invoices_')]
final class ReceivedInvoiceController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('receivedinvoice/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
