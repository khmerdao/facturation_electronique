<?php

declare(strict_types=1);

namespace App\Controller\Billing;

use App\Entity\Enum\Plan;
use App\Security\TenantContext;
use App\Service\Billing\PlanLimitChecker;
use App\Service\Billing\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages d'abonnement et de facturation SaaS.
 *
 * GET  /billing             — page d'abonnement (plan actuel + usage)
 * GET  /billing/upgrade     — page de choix de plan
 * POST /billing/checkout    — créer session Stripe Checkout
 * POST /billing/portal      — rediriger vers Stripe Customer Portal
 */
#[Route('/billing', name: 'app_billing_')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StripeService $stripeService,
        private readonly PlanLimitChecker $planLimitChecker,
    ) {}

    // ── Page principale ───────────────────────────────────────────────────────

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $usage  = $this->planLimitChecker->getUsageSummary($tenant);

        // Message de retour depuis Stripe Checkout
        $checkoutStatus = $request->query->get('status');

        if ($checkoutStatus === 'success') {
            $this->addFlash('success', '🎉 Abonnement activé ! Bienvenue dans votre nouveau plan.');
        } elseif ($checkoutStatus === 'cancelled') {
            $this->addFlash('info', 'L\'abonnement a été annulé. Vous restez sur le plan gratuit.');
        }

        return $this->render('billing/index.html.twig', [
            'tenant' => $tenant,
            'usage'  => $usage,
            'plans'  => Plan::cases(),
            'can_manage' => $tenant->getStripeCustomerId() !== null,
        ]);
    }

    // ── Page upgrade ──────────────────────────────────────────────────────────

    #[Route('/upgrade', name: 'upgrade', methods: ['GET'])]
    public function upgrade(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('billing/upgrade.html.twig', [
            'tenant'       => $tenant,
            'current_plan' => $tenant->getPlan(),
            'plans'        => Plan::cases(),
        ]);
    }

    // ── Stripe Checkout ───────────────────────────────────────────────────────

    #[Route('/checkout', name: 'checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $plan   = Plan::tryFrom($request->request->get('plan', ''));

        if (!$plan || $plan === Plan::FREE) {
            $this->addFlash('error', 'Plan invalide.');
            return $this->redirectToRoute('app_billing_upgrade');
        }

        if ($plan === $tenant->getPlan() && $tenant->hasActiveSubscription()) {
            $this->addFlash('info', 'Vous êtes déjà sur ce plan.');
            return $this->redirectToRoute('app_billing_index');
        }

        try {
            $checkoutUrl = $this->stripeService->createCheckoutSession(
                $tenant,
                $plan,
                '/billing',
            );

            return $this->redirect($checkoutUrl);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', 'Impossible de créer la session de paiement : ' . $e->getMessage());
            return $this->redirectToRoute('app_billing_upgrade');
        }
    }

    // ── Customer Portal ───────────────────────────────────────────────────────

    #[Route('/portal', name: 'portal', methods: ['POST'])]
    public function portal(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        try {
            $portalUrl = $this->stripeService->createPortalSession($tenant, '/billing');
            return $this->redirect($portalUrl);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_billing_index');
        }
    }
}
