<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Billing;

use App\Entity\Tenant;
use App\Entity\Enum\Plan;
use App\Repository\InvoiceRepository;
use App\Repository\TenantMembershipRepository;
use App\Service\Billing\LimitCheckResult;
use App\Service\Billing\PlanLimitChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de PlanLimitChecker.
 *
 * Couvre :
 *  - canCreateInvoice : FREE (limite 20), PRO (limite 500), ENTERPRISE (illimité)
 *  - canAddUser : limites par plan
 *  - LimitCheckResult : allowed, denied, usagePercent, isNearLimit
 */
final class PlanLimitCheckerTest extends TestCase
{
    private InvoiceRepository&MockObject $invoiceRepo;
    private TenantMembershipRepository&MockObject $membershipRepo;
    private PlanLimitChecker $checker;

    protected function setUp(): void
    {
        $this->invoiceRepo   = $this->createMock(InvoiceRepository::class);
        $this->membershipRepo = $this->createMock(TenantMembershipRepository::class);

        $this->checker = new PlanLimitChecker($this->invoiceRepo, $this->membershipRepo);
    }

    // ── canCreateInvoice ──────────────────────────────────────────────────────

    #[Test]
    public function free_plan_allows_invoice_under_limit(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);
        $this->invoiceRepo->method('countByFilters')->willReturn(15);

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertSame(15, $result->current);
        self::assertSame(20, $result->limit);
    }

    #[Test]
    public function free_plan_blocks_invoice_at_limit(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);
        $this->invoiceRepo->method('countByFilters')->willReturn(20);

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertFalse($result->allowed);
        self::assertNotEmpty($result->reason);
        self::assertStringContainsString('20', $result->reason);
        self::assertStringContainsString('Gratuit', $result->reason);
    }

    #[Test]
    public function pro_plan_allows_invoice_under_500(): void
    {
        $tenant = $this->makeTenant(Plan::PRO);
        $this->invoiceRepo->method('countByFilters')->willReturn(499);

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertSame(499, $result->current);
        self::assertSame(500, $result->limit);
    }

    #[Test]
    public function enterprise_plan_has_no_invoice_limit(): void
    {
        $tenant = $this->makeTenant(Plan::ENTERPRISE);
        // countByFilters ne doit PAS être appelé pour un plan illimité
        $this->invoiceRepo->expects(self::never())->method('countByFilters');

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertNull($result->limit);
    }

    // ── canAddUser ────────────────────────────────────────────────────────────

    #[Test]
    public function free_plan_allows_user_under_limit(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);
        $this->membershipRepo->method('findActiveMemberships')->willReturn([1]); // 1 membre

        $result = $this->checker->canAddUser($tenant);

        self::assertTrue($result->allowed);
        self::assertSame(2, $result->limit);
    }

    #[Test]
    public function free_plan_blocks_user_at_limit(): void
    {
        $tenant = $this->makeTenant(Plan::FREE);
        $this->membershipRepo->method('findActiveMemberships')->willReturn([1, 2]); // 2 membres

        $result = $this->checker->canAddUser($tenant);

        self::assertFalse($result->allowed);
        self::assertStringContainsString('2', $result->reason);
    }

    #[Test]
    public function enterprise_plan_has_no_user_limit(): void
    {
        $tenant = $this->makeTenant(Plan::ENTERPRISE);
        $this->membershipRepo->expects(self::never())->method('findActiveMemberships');

        $result = $this->checker->canAddUser($tenant);

        self::assertTrue($result->allowed);
        self::assertNull($result->limit);
    }

    // ── LimitCheckResult ──────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideUsagePercent')]
    public function usage_percent_calculated_correctly(
        int $current, int $limit, int $expectedPercent,
    ): void {
        $result = LimitCheckResult::allowed($current, $limit);
        self::assertSame($expectedPercent, $result->usagePercent());
    }

    public static function provideUsagePercent(): array
    {
        return [
            'zero_usage'     => [0,   20,  0],
            'half_usage'     => [10,  20,  50],
            'near_limit'     => [18,  20,  90],
            'at_limit'       => [20,  20,  100],
            'over_limit'     => [25,  20,  100], // Capped à 100
            'unlimited'      => [999, 0,   0],   // Pas de limite → 0%
        ];
    }

    #[Test]
    public function is_near_limit_at_80_percent(): void
    {
        $result = LimitCheckResult::allowed(16, 20); // 80%
        self::assertTrue($result->isNearLimit());
    }

    #[Test]
    public function is_not_near_limit_below_80_percent(): void
    {
        $result = LimitCheckResult::allowed(15, 20); // 75%
        self::assertFalse($result->isNearLimit());
    }

    #[Test]
    public function unlimited_plan_is_never_near_limit(): void
    {
        $result = LimitCheckResult::allowed(999, null);
        self::assertFalse($result->isNearLimit());
    }

    #[Test]
    public function allowed_result_has_no_reason(): void
    {
        $result = LimitCheckResult::allowed(5, 20);
        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
    }

    #[Test]
    public function denied_result_has_reason(): void
    {
        $result = LimitCheckResult::denied('Limite atteinte', 20, 20);
        self::assertFalse($result->allowed);
        self::assertSame('Limite atteinte', $result->reason);
        self::assertSame(20, $result->current);
        self::assertSame(20, $result->limit);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTenant(Plan $plan): Tenant
    {
        $tenant = new Tenant();
        $tenant->setPlan($plan);
        $tenant->setName('Test Corp');

        return $tenant;
    }
}
