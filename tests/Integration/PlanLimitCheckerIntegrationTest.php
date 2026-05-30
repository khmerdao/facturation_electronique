<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Enum\ContactType;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Enum\OnboardingStep;
use App\Entity\Enum\Plan;
use App\Entity\Enum\Role;
use App\Entity\Enum\TenantStatus;
use App\Service\Billing\PlanLimitChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration de PlanLimitChecker avec une vraie base de données.
 *
 * Vérifie que les comptages SQL sont corrects dans des conditions réelles :
 *  - Tenant FREE : canCreateInvoice() bloqué à 20 factures
 *  - Tenant FREE : canAddUser() bloqué à 2 membres
 *  - getUsageSummary() retourne les bons pourcentages
 *
 * Utilise rollback transactionnel pour isoler chaque test.
 *
 * @group integration
 * @group billing
 */
final class PlanLimitCheckerIntegrationTest extends KernelTestCase
{
    private $em;
    private PlanLimitChecker $checker;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em      = self::getContainer()->get('doctrine')->getManager();
        $this->checker = self::getContainer()->get(PlanLimitChecker::class);
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    // ── canCreateInvoice() ─────────────────────────────────────────────────

    public function test_free_plan_allows_invoice_when_under_20(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createInvoices($tenant, 19);
        $this->em->flush();

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed, 'Avec 19 factures sur 20, la création doit être autorisée');
        self::assertSame(19, $result->current);
        self::assertSame(20, $result->limit);
    }

    public function test_free_plan_blocks_invoice_at_exactly_20(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createInvoices($tenant, 20);
        $this->em->flush();

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertFalse($result->allowed, 'Avec 20 factures sur 20, la création doit être bloquée');
        self::assertSame(20, $result->current);
        self::assertSame(20, $result->limit);
        self::assertStringContainsString('20', $result->reason);
    }

    public function test_pro_plan_allows_invoice_at_499(): void
    {
        // On ne crée pas 499 factures en vrai — on vérifie juste la limite
        $tenant = $this->createTenant(Plan::PRO);
        $this->createInvoices($tenant, 5); // petit nombre pour la rapidité
        $this->em->flush();

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertSame(500, $result->limit);
    }

    public function test_enterprise_plan_always_allows_invoice(): void
    {
        $tenant = $this->createTenant(Plan::ENTERPRISE);
        $this->em->flush();

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertNull($result->limit, 'Le plan Enterprise doit avoir une limite null (illimité)');
    }

    // ── canAddUser() ───────────────────────────────────────────────────────

    public function test_free_plan_allows_second_user(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createMembership($tenant); // 1 membre
        $this->em->flush();

        $result = $this->checker->canAddUser($tenant);

        self::assertTrue($result->allowed, 'Avec 1 membre sur 2, l\'invitation doit être autorisée');
        self::assertSame(2, $result->limit);
    }

    public function test_free_plan_blocks_third_user(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createMembership($tenant); // membre 1
        $this->createMembership($tenant); // membre 2
        $this->em->flush();

        $result = $this->checker->canAddUser($tenant);

        self::assertFalse($result->allowed, 'Avec 2 membres sur 2, l\'invitation doit être bloquée');
        self::assertSame(2, $result->current);
        self::assertSame(2, $result->limit);
    }

    public function test_pro_plan_allows_up_to_10_users(): void
    {
        $tenant = $this->createTenant(Plan::PRO);
        for ($i = 0; $i < 9; $i++) {
            $this->createMembership($tenant);
        }
        $this->em->flush();

        $result = $this->checker->canAddUser($tenant);

        self::assertTrue($result->allowed);
        self::assertSame(10, $result->limit);
    }

    // ── getUsageSummary() ─────────────────────────────────────────────────

    public function test_usage_summary_returns_correct_invoice_count(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createInvoices($tenant, 10);
        $this->em->flush();

        $summary = $this->checker->getUsageSummary($tenant);

        self::assertSame(10, $summary['invoices']['current']);
        self::assertSame(20, $summary['invoices']['limit']);
        self::assertSame(50, $summary['invoices']['percent']);
    }

    public function test_usage_summary_returns_correct_user_count(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createMembership($tenant);
        $this->em->flush();

        $summary = $this->checker->getUsageSummary($tenant);

        self::assertSame(1, $summary['users']['current']);
        self::assertSame(2, $summary['users']['limit']);
        self::assertSame(50, $summary['users']['percent']);
    }

    public function test_usage_summary_enterprise_has_null_limits(): void
    {
        $tenant = $this->createTenant(Plan::ENTERPRISE);
        $this->em->flush();

        $summary = $this->checker->getUsageSummary($tenant);

        self::assertNull($summary['invoices']['limit']);
        self::assertNull($summary['users']['limit']);
        self::assertSame(0, $summary['invoices']['percent']);
    }

    public function test_usage_at_80_percent_is_near_limit(): void
    {
        $tenant = $this->createTenant(Plan::FREE);
        $this->createInvoices($tenant, 16); // 16/20 = 80%
        $this->em->flush();

        $result = $this->checker->canCreateInvoice($tenant);

        self::assertTrue($result->allowed);
        self::assertTrue($result->isNearLimit(), '80% d\'utilisation doit déclencher isNearLimit()');
    }

    // ── Isolation tenant ──────────────────────────────────────────────────

    public function test_invoices_from_other_tenant_are_not_counted(): void
    {
        $suffix  = uniqid();
        $tenantA = $this->createTenant(Plan::FREE, "Tenant A $suffix", "tenant-a-$suffix");
        $tenantB = $this->createTenant(Plan::FREE, "Tenant B $suffix", "tenant-b-$suffix");

        $this->createInvoices($tenantA, 20); // tenant A à la limite
        $this->createInvoices($tenantB, 5);  // tenant B très en dessous
        $this->em->flush();

        $resultA = $this->checker->canCreateInvoice($tenantA);
        $resultB = $this->checker->canCreateInvoice($tenantB);

        self::assertFalse($resultA->allowed, 'Tenant A doit être bloqué (20/20)');
        self::assertTrue($resultB->allowed, 'Tenant B doit être autorisé (5/20)');
        self::assertSame(5, $resultB->current);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createTenant(Plan $plan, string $name = 'Test Corp', string $slug = null): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName($name);
        $tenant->setSlug($slug ?? 'test-' . uniqid());
        $tenant->setPlan($plan);
        $tenant->setStatus(TenantStatus::ACTIVE);
        $tenant->setOnboardingCompleted(true);
        $tenant->setOnboardingStep(OnboardingStep::COMPLETED);
        $this->em->persist($tenant);
        return $tenant;
    }

    private function createInvoices(Tenant $tenant, int $count): void
    {
        $now  = new \DateTimeImmutable();
        $from = $now->modify('first day of this month');

        for ($i = 0; $i < $count; $i++) {
            $invoice = new Invoice();
            $invoice->setTenant($tenant);
            $invoice->setStatus(InvoiceStatus::DRAFT);
            $invoice->setFormat(InvoiceFormat::FACTURX);
            $invoice->setType(InvoiceType::INVOICE);
            $invoice->setCurrency('EUR');
            $invoice->setTotalHt('100.00');
            $invoice->setTotalTva('20.00');
            $invoice->setTotalTtc('120.00');
            $invoice->setAmountPaid('0.00');
            // Date dans le mois courant (pour que le filtre mensuel fonctionne)
            $invoice->setIssueDate($from->modify("+$i hours"));
            $this->em->persist($invoice);
        }
    }

    private function createMembership(Tenant $tenant): TenantMembership
    {
        $user = new User();
        $user->setEmail('user-' . uniqid() . '@test.com');
        $user->setPassword('hash');
        $this->em->persist($user);

        $membership = new TenantMembership();
        $membership->setTenant($tenant);
        $membership->setUser($user);
        $membership->setRole(Role::ACCOUNTANT);
        $this->em->persist($membership);

        return $membership;
    }
}
