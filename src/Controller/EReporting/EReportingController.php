<?php

declare(strict_types=1);

namespace App\Controller\EReporting;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E-reporting DGFiP — statuts des transmissions.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/e-reporting', name: 'app_e_reporting_')]
final class EReportingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('ereporting/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
