<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres de l'organisation.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/settings', name: 'app_settings_')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('/organisation', name: 'organisation', methods: ['GET'])]
    public function organisation(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('settings/organisation.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
