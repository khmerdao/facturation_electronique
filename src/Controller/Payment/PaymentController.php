<?php

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paiements enregistrés (entrants et sortants).
 * Stub — à compléter avec la logique métier.
 */
#[Route('/payments', name: 'app_payments_')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('payment/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
