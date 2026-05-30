<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TenantRepository;
use App\Service\Billing\StripeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Réception des webhooks Stripe.
 *
 * Endpoint PUBLIC (pas de JWT requis — Stripe n'envoie pas de token).
 * La sécurité est assurée par la vérification HMAC-SHA256 de la signature.
 *
 * POST /api/billing/webhook
 *
 * En-têtes attendus :
 *   Stripe-Signature: t=...,v1=...
 *   Content-Type: application/json
 *
 * Pour tester en local :
 *   stripe listen --forward-to localhost:8000/api/billing/webhook
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly TenantRepository $tenantRepository,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/api/billing/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        // ── 1. Vérifier la signature ──────────────────────────────────────────
        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (\RuntimeException $e) {
            $this->logger->warning('stripe.webhook.invalid_signature', [
                'error' => $e->getMessage(),
                'ip'    => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // ── 2. Extraire le tenant_id depuis les metadata ──────────────────────
        $tenantId = $this->extractTenantId($event);

        if (!$tenantId) {
            // Événement non lié à un tenant connu (ex: charge d'un other customer)
            $this->logger->debug('stripe.webhook.no_tenant_id', ['event_type' => $event['type']]);
            return new JsonResponse(['received' => true]);
        }

        $tenant = $this->tenantRepository->find($tenantId);

        if (!$tenant) {
            $this->logger->warning('stripe.webhook.tenant_not_found', [
                'tenant_id'  => $tenantId,
                'event_type' => $event['type'],
            ]);
            return new JsonResponse(['received' => true]);
        }

        // ── 3. Traiter l'événement ────────────────────────────────────────────
        try {
            $this->stripeService->handleWebhookEvent($event, $tenant);
        } catch (\Throwable $e) {
            $this->logger->error('stripe.webhook.processing_error', [
                'event_type' => $event['type'],
                'tenant_id'  => $tenantId,
                'error'      => $e->getMessage(),
            ]);

            // Retourner 500 pour que Stripe retente
            return new JsonResponse(['error' => 'Processing error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['received' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Extrait le tenant_id depuis les métadonnées de l'événement Stripe.
     * Cherche dans subscription, customer, invoice et session metadata.
     */
    private function extractTenantId(array $event): ?string
    {
        $obj = $event['data']['object'] ?? [];

        // 1. Métadonnées directes de l'objet
        $tenantId = $obj['metadata']['tenant_id'] ?? null;
        if ($tenantId) return $tenantId;

        // 2. Métadonnées de l'abonnement
        $tenantId = $obj['subscription_details']['metadata']['tenant_id'] ?? null;
        if ($tenantId) return $tenantId;

        // 3. Chercher par customer ID dans la BDD
        $customerId = $obj['customer'] ?? null;
        if ($customerId) {
            $tenant = $this->tenantRepository->findOneBy(['stripeCustomerId' => $customerId]);
            if ($tenant) return (string) $tenant->getId();
        }

        return null;
    }
}
