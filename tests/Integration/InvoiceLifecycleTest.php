<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\ContactType;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Enum\OnboardingStep;
use App\Entity\Enum\PaymentMode;
use App\Entity\Enum\Plan;
use App\Entity\Enum\TenantStatus;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceDuplicateService;
use App\Service\Invoice\InvoiceNumberingService;
use App\Service\Invoice\InvoiceStatusService;
use App\Service\Payment\PaymentService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration du cycle de vie complet d'une facture.
 *
 * Utilise le kernel Symfony réel avec une base de données de test.
 * Vérifie la cohérence de bout en bout :
 *   DRAFT → VALIDATED (avec numérotation) → SENT → ACKNOWLEDGED → PAID
 *   + Duplication + Création d'avoir
 *
 * @group integration
 */
final class InvoiceLifecycleTest extends KernelTestCase
{
    private $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        // Schéma propre pour chaque test
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    // ── Cycle complet ─────────────────────────────────────────────────────────

    public function test_full_lifecycle(): void
    {
        [$tenant, $user, $contact] = $this->createTestContext();

        // ── 1. Créer la facture en DRAFT ──────────────────────────────────────
        $invoice = $this->createInvoice($tenant, $contact);

        self::assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
        self::assertNull($invoice->getNumber());

        // ── 2. Valider → VALIDATED + numérotation ─────────────────────────────
        $statusService = self::getContainer()->get(InvoiceStatusService::class);

        $statusService->validate($invoice, $user);

        self::assertSame(InvoiceStatus::VALIDATED, $invoice->getStatus());
        self::assertNotNull($invoice->getNumber());
        self::assertMatchesRegularExpression('/^FAC-\d{4}-\d{4}$/', $invoice->getNumber());
        self::assertNotNull($invoice->getValidatedAt());
        self::assertSame('ACME SAS', $invoice->getClientNameSnapshot());

        // ── 3. Envoyer → SENT ─────────────────────────────────────────────────
        $statusService->markAsSent($invoice);

        self::assertSame(InvoiceStatus::SENT, $invoice->getStatus());

        // ── 4. Acquittement → ACKNOWLEDGED ────────────────────────────────────
        $statusService->markAsAcknowledged($invoice, 'AR positif DGFiP');

        self::assertSame(InvoiceStatus::ACKNOWLEDGED, $invoice->getStatus());
        $lastHistory = $invoice->getStatusHistory()->last();
        self::assertSame('AR positif DGFiP', $lastHistory->getComment());

        // ── 5. Paiement → PAID ────────────────────────────────────────────────
        $paymentService = self::getContainer()->get(PaymentService::class);
        $payment = $paymentService->recordOnInvoice($invoice, [
            'amount'   => $invoice->getTotalTtc(),
            'date'     => new \DateTimeImmutable(),
            'mode'     => PaymentMode::VIREMENT,
            'currency' => 'EUR',
        ], $user);

        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        self::assertSame($invoice->getTotalTtc(), $invoice->getAmountPaid());
        self::assertNotNull($invoice->getPaidAt());
        self::assertSame('0.00', self::getContainer()->get(InvoiceCalculatorService::class)->getRemainingDue($invoice));
    }

    // ── Historique de statuts ─────────────────────────────────────────────────

    public function test_status_history_is_complete(): void
    {
        [$tenant, $user, $contact] = $this->createTestContext();
        $invoice = $this->createInvoice($tenant, $contact);

        $statusService = self::getContainer()->get(InvoiceStatusService::class);
        $statusService->validate($invoice, $user);
        $statusService->markAsSent($invoice);
        $statusService->markAsAcknowledged($invoice);

        $history = $invoice->getStatusHistory()->toArray();

        self::assertCount(3, $history);
        self::assertSame(InvoiceStatus::DRAFT,        $history[0]->getFromStatus());
        self::assertSame(InvoiceStatus::VALIDATED,     $history[0]->getToStatus());
        self::assertSame(InvoiceStatus::VALIDATED,     $history[1]->getFromStatus());
        self::assertSame(InvoiceStatus::SENT,          $history[1]->getToStatus());
        self::assertSame(InvoiceStatus::SENT,          $history[2]->getFromStatus());
        self::assertSame(InvoiceStatus::ACKNOWLEDGED,  $history[2]->getToStatus());
    }

    // ── Duplication ───────────────────────────────────────────────────────────

    public function test_duplicate_creates_new_draft(): void
    {
        [$tenant, $user, $contact] = $this->createTestContext();
        $invoice = $this->createInvoice($tenant, $contact);

        $statusService   = self::getContainer()->get(InvoiceStatusService::class);
        $duplicateService = self::getContainer()->get(InvoiceDuplicateService::class);

        $statusService->validate($invoice, $user);

        $copy = $duplicateService->duplicate($invoice, $user);

        self::assertSame(InvoiceStatus::DRAFT, $copy->getStatus());
        self::assertNull($copy->getNumber());
        self::assertNotSame($invoice->getId(), $copy->getId());
        self::assertCount($invoice->getLines()->count(), $copy->getLines());
        self::assertSame($invoice->getTotalHt(), $copy->getTotalHt());
    }

    // ── Avoir ─────────────────────────────────────────────────────────────────

    public function test_create_credit_note_from_acknowledged_invoice(): void
    {
        [$tenant, $user, $contact] = $this->createTestContext();
        $invoice = $this->createInvoice($tenant, $contact);

        $statusService    = self::getContainer()->get(InvoiceStatusService::class);
        $duplicateService = self::getContainer()->get(InvoiceDuplicateService::class);

        $statusService->validate($invoice, $user);
        $statusService->markAsSent($invoice);
        $statusService->markAsAcknowledged($invoice);

        $creditNote = $duplicateService->createCreditNote($invoice, $user, 'Erreur de facturation');

        self::assertSame(InvoiceStatus::DRAFT,       $creditNote->getStatus());
        self::assertSame(InvoiceType::CREDIT_NOTE,   $creditNote->getType());
        self::assertSame($invoice,                    $creditNote->getCreditNoteFor());
        self::assertSame($invoice->getTotalHt(),      $creditNote->getTotalHt());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createTestContext(): array
    {
        // Tenant
        $tenant = new Tenant();
        $tenant->setName('Test Corp SAS');
        $tenant->setSlug('test-corp-' . uniqid());
        $tenant->setPlan(Plan::PRO);
        $tenant->setStatus(TenantStatus::ACTIVE);
        $tenant->setOnboardingCompleted(true);
        $tenant->setOnboardingStep(OnboardingStep::COMPLETED);
        $this->em->persist($tenant);

        // Séquence
        $seq = new InvoiceSequence();
        $seq->setTenant($tenant);
        $seq->setName('Test');
        $seq->setPrefix('FAC');
        $seq->setYearFormat('AAAA');
        $seq->setSeparator('-');
        $seq->setPadding(4);
        $seq->setNextNumber(1);
        $seq->setStartNumber(1);
        $seq->setResetYearly(false);
        $this->em->persist($seq);

        // User
        $user = new User();
        $user->setEmail('test-' . uniqid() . '@test.com');
        $user->setPassword('hash');
        $this->em->persist($user);

        // Contact
        $contact = new Contact();
        $contact->setTenant($tenant);
        $contact->setName('ACME SAS');
        $contact->setType(ContactType::CLIENT);
        $contact->setSiret('35600000000048');
        $this->em->persist($contact);

        $this->em->flush();

        return [$tenant, $user, $contact];
    }

    private function createInvoice(Tenant $tenant, Contact $contact): Invoice
    {
        // Trouver la séquence du tenant
        $seq = $this->em->getRepository(InvoiceSequence::class)->findOneBy(['tenant' => $tenant]);

        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setContact($contact);
        $invoice->setStatus(InvoiceStatus::DRAFT);
        $invoice->setFormat(InvoiceFormat::FACTURX);
        $invoice->setType(InvoiceType::INVOICE);
        $invoice->setCurrency('EUR');
        $invoice->setSubject('Test lifecycle');
        $invoice->setSequence($seq);

        $line = new InvoiceLine();
        $line->setInvoice($invoice);
        $line->setDescription('Prestation de test');
        $line->setQuantity('2');
        $line->setUnit('H');
        $line->setUnitPrice('100.00');
        $line->setDiscount('0.00');
        $line->setTvaRate('20.00');
        $line->setPosition(0);
        $invoice->addLine($line);

        // Calculer les montants
        $calculator = self::getContainer()->get(InvoiceCalculatorService::class);
        $calculator->recalculate($invoice);

        $this->em->persist($invoice);
        $this->em->persist($line);
        $this->em->flush();

        return $invoice;
    }
}
