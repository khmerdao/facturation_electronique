<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Tenant;
use App\Entity\Enum\Plan;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service d'intégration Stripe.
 *
 * Gère le cycle de vie des abonnements :
 *  - Création d'un customer Stripe
 *  - Création d'une session Checkout (redirection vers Stripe)
 *  - URL du Customer Portal (gestion par le client)
 *  - Traitement des webhooks (changement de statut d'abonnement)
 *  - Mise à jour du plan du tenant selon l'abonnement Stripe
 *
 * Configuration requise dans .env :
 *   STRIPE_SECRET_KEY=sk_live_...
 *   STRIPE_WEBHOOK_SECRET=whsec_...
 *   STRIPE_PRICE_PRO=price_...
 *   STRIPE_PRICE_ENTERPRISE=price_...
 *
 * Note : l'intégration utilise l'API HTTP Stripe directement pour éviter
 * la dépendance au SDK stripe/stripe-php. En production, ajouter le SDK
 * via composer pour bénéficier des types et des helpers de signature.
 */
final class StripeService
{
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
        private readonly string $stripePricePro,
        private readonly string $stripePriceEnterprise,
        private readonly string $appUrl,
    ) {}

    // ── Customer ──────────────────────────────────────────────────────────────

    /**
     * Crée ou récupère le customer Stripe pour un tenant.
     * Stocke le stripeCustomerId sur le tenant si nouveau.
     */
    public function getOrCreateCustomer(Tenant $tenant): string
    {
        if ($tenant->getStripeCustomerId()) {
            return $tenant->getStripeCustomerId();
        }

        $response = $this->stripePost('/customers', [
            'email'       => $tenant->getBillingEmail() ?? '',
            'name'        => $tenant->getName(),
            'metadata'    => ['tenant_id' => (string) $tenant->getId()],
        ]);

        $customerId = $response['id'];
        $tenant->setStripeCustomerId($customerId);
        $this->em->flush();

        $this->logger->info('stripe.customer.created', [
            'tenant_id'   => (string) $tenant->getId(),
            'customer_id' => $customerId,
        ]);

        return $customerId;
    }

    // ── Checkout ──────────────────────────────────────────────────────────────

    /**
     * Crée une session Stripe Checkout pour s'abonner à un plan.
     * Retourne l'URL de redirection vers Stripe.
     *
     * @throws \RuntimeException si le plan n'a pas de prix configuré
     */
    public function createCheckoutSession(Tenant $tenant, Plan $plan, string $returnPath = '/billing'): string
    {
        $priceId    = $this->getPriceId($plan);
        $customerId = $this->getOrCreateCustomer($tenant);

        $response = $this->stripePost('/checkout/sessions', [
            'customer'              => $customerId,
            'mode'                  => 'subscription',
            'payment_method_types'  => ['card'],
            'line_items'            => [[
                'price'    => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $this->appUrl . $returnPath . '?session_id={CHECKOUT_SESSION_ID}&status=success',
            'cancel_url'  => $this->appUrl . $returnPath . '?status=cancelled',
            'metadata'    => ['tenant_id' => (string) $tenant->getId()],
            'subscription_data' => [
                'metadata' => ['tenant_id' => (string) $tenant->getId()],
                'trial_period_days' => 30, // 30 jours d'essai gratuit
            ],
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'auto',
            'tax_id_collection' => ['enabled' => true],
        ]);

        $this->logger->info('stripe.checkout.created', [
            'tenant_id'  => (string) $tenant->getId(),
            'plan'       => $plan->value,
            'session_id' => $response['id'],
        ]);

        return $response['url'];
    }

    // ── Customer Portal ───────────────────────────────────────────────────────

    /**
     * Crée une session Customer Portal Stripe.
     * Permet au client de gérer son abonnement (upgrade, annulation, facturation).
     *
     * @throws \RuntimeException si le tenant n'a pas de customer Stripe
     */
    public function createPortalSession(Tenant $tenant, string $returnUrl = '/billing'): string
    {
        $customerId = $tenant->getStripeCustomerId();

        if (!$customerId) {
            throw new \RuntimeException(
                'Ce tenant n\'a pas encore de compte de facturation. '
                . 'Souscrivez d\'abord à un abonnement.'
            );
        }

        $response = $this->stripePost('/billing_portal/sessions', [
            'customer'    => $customerId,
            'return_url'  => $this->appUrl . $returnUrl,
        ]);

        return $response['url'];
    }

    // ── Webhooks ──────────────────────────────────────────────────────────────

    /**
     * Vérifie la signature d'un webhook Stripe et retourne le payload décodé.
     *
     * @throws \RuntimeException si la signature est invalide
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): array
    {
        // Vérification de signature HMAC-SHA256
        $parts     = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $signatures[] = substr($part, 3);
            }
        }

        if (!$timestamp || empty($signatures)) {
            throw new \RuntimeException('Signature Stripe invalide : en-tête malformé.');
        }

        // Tolérance de 300 secondes (protection replay)
        if (abs(time() - (int) $timestamp) > 300) {
            throw new \RuntimeException('Webhook Stripe expiré (tolérance 300s dépassée).');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSig   = hash_hmac('sha256', $signedPayload, $this->stripeWebhookSecret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSig, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new \RuntimeException('Signature Stripe invalide.');
        }

        return json_decode($payload, true) ?? [];
    }

    /**
     * Traite un événement Stripe et met à jour le tenant correspondant.
     * Appelé par StripeWebhookController après vérification de la signature.
     */
    public function handleWebhookEvent(array $event, Tenant $tenant): void
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        $this->logger->info('stripe.webhook.received', [
            'type'       => $type,
            'tenant_id'  => (string) $tenant->getId(),
        ]);

        match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated'  => $this->syncSubscription($tenant, $data),
            'customer.subscription.deleted'  => $this->handleSubscriptionDeleted($tenant, $data),
            'invoice.paid'                   => $this->handleInvoicePaid($tenant, $data),
            'invoice.payment_failed'         => $this->handlePaymentFailed($tenant, $data),
            default                          => null,
        };
    }

    // ── Récupération d'un abonnement ──────────────────────────────────────────

    /**
     * Récupère les détails d'un abonnement depuis Stripe.
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->stripeGet('/subscriptions/' . $subscriptionId);
    }

    // ── Synchronisation du tenant ─────────────────────────────────────────────

    /**
     * Synchronise le statut d'abonnement Stripe avec le plan du tenant.
     * Mappe le price_id Stripe vers le Plan enum applicatif.
     */
    public function syncSubscription(Tenant $tenant, array $subscriptionData): void
    {
        $status      = $subscriptionData['status'] ?? 'inactive';
        $priceId     = $subscriptionData['items']['data'][0]['price']['id'] ?? null;
        $periodEnd   = isset($subscriptionData['current_period_end'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $subscriptionData['current_period_end'])
            : null;
        $cancelAtEnd = (bool) ($subscriptionData['cancel_at_period_end'] ?? false);
        $subId       = $subscriptionData['id'] ?? null;

        // Mettre à jour les métadonnées Stripe sur le tenant
        $tenant->setStripeSubscriptionId($subId);
        $tenant->setStripeSubscriptionStatus($status);
        $tenant->setCurrentPeriodEnd($periodEnd);
        $tenant->setCancelAtPeriodEnd($cancelAtEnd);
        if ($priceId) {
            $tenant->setStripePriceId($priceId);
        }

        // Mapper le price_id → Plan si l'abonnement est actif
        if (in_array($status, ['active', 'trialing'], true) && $priceId) {
            $plan = $this->mapPriceIdToPlan($priceId);
            if ($plan && $tenant->getPlan() !== $plan) {
                $tenant->setPlan($plan);
                $this->logger->info('stripe.plan.upgraded', [
                    'tenant_id' => (string) $tenant->getId(),
                    'plan'      => $plan->value,
                ]);
            }
        }

        $this->em->flush();
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function handleSubscriptionDeleted(Tenant $tenant, array $data): void
    {
        $tenant->setStripeSubscriptionStatus('canceled');
        $tenant->setCancelAtPeriodEnd(false);
        $tenant->setPlan(Plan::FREE);
        $this->em->flush();

        $this->logger->info('stripe.subscription.deleted', ['tenant_id' => (string) $tenant->getId()]);
    }

    private function handleInvoicePaid(Tenant $tenant, array $data): void
    {
        // L'abonnement est payé — mettre à jour la période courante si disponible
        $subscriptionId = $data['subscription'] ?? null;
        if ($subscriptionId && $subscriptionId === $tenant->getStripeSubscriptionId()) {
            try {
                $sub = $this->getSubscription($subscriptionId);
                $this->syncSubscription($tenant, $sub);
            } catch (\Throwable $e) {
                $this->logger->warning('stripe.invoice_paid.sync_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    private function handlePaymentFailed(Tenant $tenant, array $data): void
    {
        $tenant->setStripeSubscriptionStatus('past_due');
        $this->em->flush();

        $this->logger->warning('stripe.payment.failed', ['tenant_id' => (string) $tenant->getId()]);
    }

    private function getPriceId(Plan $plan): string
    {
        return match ($plan) {
            Plan::PRO        => $this->stripePricePro,
            Plan::ENTERPRISE => $this->stripePriceEnterprise,
            Plan::FREE       => throw new \RuntimeException('Le plan gratuit n\'a pas de price Stripe.'),
        };
    }

    private function mapPriceIdToPlan(string $priceId): ?Plan
    {
        return match ($priceId) {
            $this->stripePricePro        => Plan::PRO,
            $this->stripePriceEnterprise => Plan::ENTERPRISE,
            default                      => null,
        };
    }

    private function stripePost(string $endpoint, array $data): array
    {
        $response = $this->httpClient->request('POST', self::STRIPE_API_BASE . $endpoint, [
            'auth_basic' => [$this->stripeSecretKey, ''],
            'body'       => $this->flattenForStripe($data),
            'headers'    => ['Stripe-Version' => '2024-06-20'],
        ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->toArray(false);

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'Erreur Stripe %d : %s',
                $statusCode,
                $body['error']['message'] ?? 'Erreur inconnue',
            ));
        }

        return $body;
    }

    private function stripeGet(string $endpoint): array
    {
        $response = $this->httpClient->request('GET', self::STRIPE_API_BASE . $endpoint, [
            'auth_basic' => [$this->stripeSecretKey, ''],
            'headers'    => ['Stripe-Version' => '2024-06-20'],
        ]);

        return $response->toArray(false);
    }

    /**
     * Stripe attend des données form-encoded plates (pas de JSON imbriqué).
     * Ex: line_items[0][price] = price_xxx
     */
    private function flattenForStripe(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenForStripe($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }
}
