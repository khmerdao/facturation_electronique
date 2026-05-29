<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Centre de notifications.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/notifications', name: 'app_notifications_')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('notification/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
