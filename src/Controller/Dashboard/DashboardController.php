<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dashboard principal — KPIs, graphiques, alertes en temps réel.
 */
#[Route('/dashboard', name: 'app_dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('dashboard/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
