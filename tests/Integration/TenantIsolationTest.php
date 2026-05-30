<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\Tenant;
use App\Entity\Enum\ContactType;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Enum\OnboardingStep;
use App\Entity\Enum\Plan;
use App\Entity\Enum\TenantStatus;
use App\Repository\InvoiceRepository;
use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration de l'isolation multi-tenant.
 *
 * Vérifie que le TenantFilter Doctrine empêche l'accès
 * aux données d'un autre tenant même avec des requêtes directes.
 *
 * @group integration
 * @group security
 */
final class TenantIsolationTest extends KernelTestCase
{
    private $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    // ── TenantFilter Doctrine ─────────────────────────────────────────────────

    public function test_invoice_query_returns_only_current_tenant_data(): void
    {
        [$tenantA, $tenantB] = $this->createTwoTenants();

        $invoiceA = $this->createInvoice($tenantA, 'Facture tenant A');
        $invoiceB = $this->createInvoice($tenantB, 'Facture tenant B');
        $this->em->flush();

        // Activer le filtre pour le tenant A
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant_id', (string) $tenantA->getId(), 'string');

        /** @var InvoiceRepository $repo */
        $repo = $this->em->getRepository(Invoice::class);
        $allInvoices = $repo->findAll();

        // Avec le filtre actif, seules les factures du tenant A sont visibles
        foreach ($allInvoices as $inv) {
            self::assertSame(
                (string) $tenantA->getId(),
                (string) $inv->getTenant()->getId(),
                'Le TenantFilter doit exclure les factures des autres tenants',
            );
        }

        // Désactiver le filtre
        $this->em->getFilters()->disable('tenant_filter');
    }

    public function test_contact_query_returns_only_current_tenant_data(): void
    {
        [$tenantA, $tenantB] = $this->createTwoTenants();

        $contactA = $this->createContact($tenantA, 'Client de A');
        $contactB = $this->createContact($tenantB, 'Client de B');
        $this->em->flush();

        // Activer le filtre pour le tenant B
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant_id', (string) $tenantB->getId(), 'string');

        $contacts = $this->em->getRepository(Contact::class)->findAll();

        foreach ($contacts as $contact) {
            self::assertSame(
                (string) $tenantB->getId(),
                (string) $contact->getTenant()->getId(),
                'Le TenantFilter doit exclure les contacts des autres tenants',
            );
        }

        $this->em->getFilters()->disable('tenant_filter');
    }

    public function test_without_filter_all_tenants_are_visible(): void
    {
        [$tenantA, $tenantB] = $this->createTwoTenants();

        $this->createInvoice($tenantA, 'Facture A');
        $this->createInvoice($tenantB, 'Facture B');
        $this->em->flush();

        // Sans filtre actif : les deux factures sont visibles (pour le super-admin)
        $this->em->getFilters()->disable('tenant_filter');

        $allInvoices = $this->em->getRepository(Invoice::class)->findAll();

        $tenantIds = array_unique(array_map(
            fn($inv) => (string) $inv->getTenant()->getId(),
            $allInvoices,
        ));

        // Les deux tenants doivent être représentés
        self::assertContains((string) $tenantA->getId(), $tenantIds);
        self::assertContains((string) $tenantB->getId(), $tenantIds);
    }

    // ── Slugs uniques ─────────────────────────────────────────────────────────

    public function test_two_tenants_have_distinct_slugs(): void
    {
        [$tenantA, $tenantB] = $this->createTwoTenants();

        self::assertNotSame($tenantA->getSlug(), $tenantB->getSlug());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createTwoTenants(): array
    {
        $suffix = uniqid();
        $tenantA = $this->createTenant("Tenant A $suffix", "tenant-a-$suffix");
        $tenantB = $this->createTenant("Tenant B $suffix", "tenant-b-$suffix");

        $this->em->flush();

        return [$tenantA, $tenantB];
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName($name);
        $tenant->setSlug($slug);
        $tenant->setPlan(Plan::FREE);
        $tenant->setStatus(TenantStatus::ACTIVE);
        $tenant->setOnboardingCompleted(true);
        $tenant->setOnboardingStep(OnboardingStep::COMPLETED);
        $this->em->persist($tenant);

        return $tenant;
    }

    private function createInvoice(Tenant $tenant, string $subject): Invoice
    {
        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setStatus(InvoiceStatus::DRAFT);
        $invoice->setFormat(InvoiceFormat::FACTURX);
        $invoice->setType(InvoiceType::INVOICE);
        $invoice->setCurrency('EUR');
        $invoice->setSubject($subject);
        $invoice->setTotalHt('0.00');
        $invoice->setTotalTva('0.00');
        $invoice->setTotalTtc('0.00');
        $this->em->persist($invoice);

        return $invoice;
    }

    private function createContact(Tenant $tenant, string $name): Contact
    {
        $contact = new Contact();
        $contact->setTenant($tenant);
        $contact->setName($name);
        $contact->setType(ContactType::CLIENT);
        $this->em->persist($contact);

        return $contact;
    }
}
