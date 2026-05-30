<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Billing;

use App\Entity\Tenant;
use App\Entity\Enum\Plan;
use App\Service\Billing\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests unitaires de StripeService — validation de signature webhook.
 *
 * Couvre :
 *  - constructWebhookEvent() : signature HMAC-SHA256 valide
 *  - constructWebhookEvent() : signature invalide → RuntimeException
 *  - constructWebhookEvent() : timestamp expiré (> 300s) → RuntimeException
 *  - constructWebhookEvent() : header malformé → RuntimeException
 *  - syncSubscription() : statut active → upgrade du plan
 *  - syncSubscription() : statut canceled → downgrade FREE
 *  - syncSubscription() : met à jour currentPeriodEnd
 */
final class StripeServiceWebhookTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_secret_key_for_unit_tests';
    private const PRICE_PRO      = 'price_test_pro_123';
    private const PRICE_ENT      = 'price_test_enterprise_456';

    private StripeService $service;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('flush');

        $this->service = new StripeService(
            httpClient:            $this->createMock(HttpClientInterface::class),
            em:                    $this->em,
            logger:                new NullLogger(),
            stripeSecretKey:       'sk_test_key',
            stripeWebhookSecret:   self::WEBHOOK_SECRET,
            stripePricePro:        self::PRICE_PRO,
            stripePriceEnterprise: self::PRICE_ENT,
            appUrl:                'http://localhost:8000',
        );
    }

    // ── constructWebhookEvent() — succès ──────────────────────────────────

    #[Test]
    public function valid_signature_returns_decoded_event(): void
    {
        $payload   = json_encode(['type' => 'customer.subscription.updated', 'data' => []]);
        $timestamp = (string) time();
        $sig       = $this->makeSignature($timestamp, $payload);

        $event = $this->service->constructWebhookEvent($payload, "t={$timestamp},v1={$sig}");

        self::assertArrayHasKey('type', $event);
        self::assertSame('customer.subscription.updated', $event['type']);
    }

    #[Test]
    public function accepts_multiple_v1_signatures_when_one_is_valid(): void
    {
        $payload   = json_encode(['type' => 'test']);
        $timestamp = (string) time();
        $validSig  = $this->makeSignature($timestamp, $payload);
        $fakeSig   = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        // Stripe peut envoyer plusieurs signatures (rollover de clés)
        $sigHeader = "t={$timestamp},v1={$fakeSig},v1={$validSig}";

        $event = $this->service->constructWebhookEvent($payload, $sigHeader);

        self::assertIsArray($event);
    }

    // ── constructWebhookEvent() — erreurs ─────────────────────────────────

    #[Test]
    public function invalid_signature_throws_runtime_exception(): void
    {
        $payload   = json_encode(['type' => 'test']);
        $timestamp = (string) time();
        $fakeSig   = str_repeat('a', 64);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/signature.*invalide/i');

        $this->service->constructWebhookEvent($payload, "t={$timestamp},v1={$fakeSig}");
    }

    #[Test]
    public function expired_timestamp_throws_runtime_exception(): void
    {
        $payload   = json_encode(['type' => 'test']);
        $timestamp = (string) (time() - 400); // 400s > 300s de tolérance
        $sig       = $this->makeSignature($timestamp, $payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/expir/i');

        $this->service->constructWebhookEvent($payload, "t={$timestamp},v1={$sig}");
    }

    #[Test]
    public function future_timestamp_beyond_tolerance_throws_exception(): void
    {
        $payload   = json_encode(['type' => 'test']);
        $timestamp = (string) (time() + 400); // Dans le futur au-delà de la tolérance
        $sig       = $this->makeSignature($timestamp, $payload);

        $this->expectException(\RuntimeException::class);

        $this->service->constructWebhookEvent($payload, "t={$timestamp},v1={$sig}");
    }

    #[Test]
    public function malformed_header_missing_timestamp_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/malform/i');

        $this->service->constructWebhookEvent('{}', 'v1=abc123');
    }

    #[Test]
    public function malformed_header_missing_v1_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->constructWebhookEvent('{}', 't=' . time());
    }

    #[Test]
    public function empty_signature_header_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->constructWebhookEvent('{}', '');
    }

    // ── syncSubscription() ────────────────────────────────────────────────

    #[Test]
    public function sync_active_pro_subscription_upgrades_plan(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);
        $this->em->expects(self::once())->method('flush');

        $this->service->syncSubscription($tenant, [
            'id'                  => 'sub_test123',
            'status'              => 'active',
            'current_period_end'  => time() + 2592000,
            'cancel_at_period_end' => false,
            'items'               => ['data' => [['price' => ['id' => self::PRICE_PRO]]]],
        ]);

        self::assertSame(Plan::PRO, $tenant->getPlan());
        self::assertSame('active', $tenant->getStripeSubscriptionStatus());
        self::assertSame('sub_test123', $tenant->getStripeSubscriptionId());
    }

    #[Test]
    public function sync_trialing_enterprise_upgrades_plan(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);

        $this->service->syncSubscription($tenant, [
            'id'     => 'sub_ent123',
            'status' => 'trialing',
            'current_period_end'  => time() + 2592000,
            'cancel_at_period_end' => false,
            'items'  => ['data' => [['price' => ['id' => self::PRICE_ENT]]]],
        ]);

        self::assertSame(Plan::ENTERPRISE, $tenant->getPlan());
        self::assertSame('trialing', $tenant->getStripeSubscriptionStatus());
    }

    #[Test]
    public function sync_canceled_subscription_downgrades_to_free(): void
    {
        $tenant = $this->makeTenant(Plan::PRO);
        $tenant->setStripeSubscriptionId('sub_old');

        $this->service->handleWebhookEvent([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_old', 'status' => 'canceled']],
        ], $tenant);

        self::assertSame(Plan::FREE, $tenant->getPlan());
        self::assertSame('canceled', $tenant->getStripeSubscriptionStatus());
    }

    #[Test]
    public function sync_past_due_does_not_change_plan(): void
    {
        $tenant = $this->makeTenant(Plan::PRO);

        $this->service->syncSubscription($tenant, [
            'id'     => 'sub_test',
            'status' => 'past_due', // pas active/trialing → plan inchangé
            'current_period_end'   => time() + 86400,
            'cancel_at_period_end' => false,
            'items' => ['data' => [['price' => ['id' => self::PRICE_PRO]]]],
        ]);

        // Le plan ne change pas pour past_due
        self::assertSame(Plan::PRO, $tenant->getPlan());
        self::assertSame('past_due', $tenant->getStripeSubscriptionStatus());
    }

    #[Test]
    public function sync_sets_current_period_end(): void
    {
        $tenant    = $this->makeTenant(Plan::FREE);
        $futureTs  = time() + 2592000;

        $this->service->syncSubscription($tenant, [
            'id'                  => 'sub_test',
            'status'              => 'active',
            'current_period_end'  => $futureTs,
            'cancel_at_period_end' => false,
            'items'               => ['data' => [['price' => ['id' => self::PRICE_PRO]]]],
        ]);

        self::assertNotNull($tenant->getCurrentPeriodEnd());
        self::assertSame(
            (new \DateTimeImmutable())->setTimestamp($futureTs)->format('Y-m-d'),
            $tenant->getCurrentPeriodEnd()->format('Y-m-d')
        );
    }

    #[Test]
    public function sync_sets_cancel_at_period_end_flag(): void
    {
        $tenant = $this->makeTenant(Plan::PRO);

        $this->service->syncSubscription($tenant, [
            'id'                  => 'sub_test',
            'status'              => 'active',
            'current_period_end'  => time() + 86400,
            'cancel_at_period_end' => true,
            'items'               => ['data' => [['price' => ['id' => self::PRICE_PRO]]]],
        ]);

        self::assertTrue($tenant->isCancelAtPeriodEnd());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeTenant(Plan $plan): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName('Test Corp');
        $tenant->setPlan($plan);
        return $tenant;
    }

    /**
     * Calcule la signature HMAC-SHA256 comme le fait Stripe.
     */
    private function makeSignature(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);
    }
}
