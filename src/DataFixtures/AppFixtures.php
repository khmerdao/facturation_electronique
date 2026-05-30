<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceSequence;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ReceivedInvoice;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Enum\ContactType;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Entity\Enum\OnboardingStep;
use App\Entity\Enum\PaymentDirection;
use App\Entity\Enum\PaymentMode;
use App\Entity\Enum\Plan;
use App\Entity\Enum\ProductType;
use App\Entity\Enum\ReceivedInvoiceStatus;
use App\Entity\Enum\Role;
use App\Entity\Enum\TenantStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de développement.
 *
 * Crée un environnement complet pour tester l'application :
 *   - 1 tenant ACME SAS (plan PRO, onboarding complété)
 *   - 2 utilisateurs : owner + comptable
 *   - 10 contacts (6 clients + 4 fournisseurs)
 *   - 15 produits à taux de TVA variés
 *   - 20 factures à différents statuts
 *   - 5 factures reçues
 *   - Quelques paiements
 *
 * Usage : php bin/console doctrine:fixtures:load
 */
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Tenant ────────────────────────────────────────────────────────────
        $tenant = new Tenant();
        $tenant->setName('ACME SAS');
        $tenant->setSlug('acme-sas-demo');
        $tenant->setSiret('35600000000048');
        $tenant->setTvaIntra('FR12356000000');
        $tenant->setBillingEmail('compta@acme-demo.fr');
        $tenant->setPhone('01 23 45 67 89');
        $tenant->setIban('FR7630006000011234567890189');
        $tenant->setBic('BNPAFRPP');
        $tenant->setPlan(Plan::PRO);
        $tenant->setStatus(TenantStatus::ACTIVE);
        $tenant->setOnboardingCompleted(true);
        $tenant->setOnboardingStep(OnboardingStep::COMPLETED);
        $tenant->setLegalMentions('SAS au capital de 10 000 € — RCS Paris 356 000 000');
        $tenant->setShareCapital(10000);
        $tenant->setLegalForm('SAS');

        $addr = $tenant->getAddress();
        $addr->setLine1('12 rue de la Paix');
        $addr->setPostalCode('75001');
        $addr->setCity('Paris');
        $addr->setCountry('FR');

        $manager->persist($tenant);

        // ── Séquence de numérotation ──────────────────────────────────────────
        $sequence = new InvoiceSequence();
        $sequence->setTenant($tenant);
        $sequence->setName('Séquence principale');
        $sequence->setPrefix('FAC');
        $sequence->setYearFormat('AAAA');
        $sequence->setSeparator('-');
        $sequence->setPadding(4);
        $sequence->setStartNumber(1);
        $sequence->setNextNumber(21); // On commence après les 20 fixtures
        $sequence->setResetYearly(false);
        $manager->persist($sequence);

        // ── Utilisateurs ──────────────────────────────────────────────────────
        $owner = new User();
        $owner->setEmail('admin@demo.test');
        $owner->setPassword($this->hasher->hashPassword($owner, 'password'));
        $owner->setFirstName('Alice');
        $owner->setLastName('Martin');
        $owner->setEmailVerified(true);
        $manager->persist($owner);

        $accountant = new User();
        $accountant->setEmail('comptable@demo.test');
        $accountant->setPassword($this->hasher->hashPassword($accountant, 'password'));
        $accountant->setFirstName('Bob');
        $accountant->setLastName('Dupont');
        $accountant->setEmailVerified(true);
        $manager->persist($accountant);

        // ── Memberships ───────────────────────────────────────────────────────
        foreach ([[$owner, Role::OWNER], [$accountant, Role::ACCOUNTANT]] as [$user, $role]) {
            $m = new TenantMembership();
            $m->setTenant($tenant);
            $m->setUser($user);
            $m->setRole($role);
            $m->setJoinedAt(new \DateTimeImmutable('-6 months'));
            $manager->persist($m);
        }

        // ── Contacts clients ──────────────────────────────────────────────────
        $clientsData = [
            ['TechCorp SARL',   'CLIENT',   '12345678901234', 'FR12345678901', 'contact@techcorp.fr'],
            ['Innovate SAS',    'CLIENT',   '23456789012345', null,            'factures@innovate.fr'],
            ['Digital Media',   'CLIENT',   '34567890123456', 'FR34567890123', 'billing@digitalmedia.fr'],
            ['StartUp Lab',     'CLIENT',   '45678901234567', null,            'admin@startuplab.fr'],
            ['Consulting Plus', 'CLIENT',   '56789012345678', 'FR56789012345', 'comptabilite@consulting-plus.fr'],
            ['Retail Express',  'CLIENT',   '67890123456789', null,            'finance@retailexpress.fr'],
        ];

        $contacts = [];
        foreach ($clientsData as [$name, $type, $siret, $tva, $email]) {
            $c = new Contact();
            $c->setTenant($tenant);
            $c->setName($name);
            $c->setType(ContactType::from($type));
            $c->setSiret($siret);
            $c->setTvaIntra($tva);
            $c->setEmail($email);
            $c->setBillingEmail($email);
            $c->getAddress()->setPostalCode('75008')->setCity('Paris')->setCountry('FR');
            $manager->persist($c);
            $contacts[] = $c;
        }

        // ── Contacts fournisseurs ──────────────────────────────────────────────
        $suppliersData = [
            ['Office Supplies Co',  '78901234567890', 'office@supplies.fr'],
            ['Cloud Hosting SAS',   '89012345678901', 'billing@cloudhosting.fr'],
            ['Print & Design',      '90123456789012', 'devis@printdesign.fr'],
            ['Software License',    '01234567890123', 'licenses@software.fr'],
        ];

        $suppliers = [];
        foreach ($suppliersData as [$name, $siret, $email]) {
            $s = new Contact();
            $s->setTenant($tenant);
            $s->setName($name);
            $s->setType(ContactType::SUPPLIER);
            $s->setSiret($siret);
            $s->setEmail($email);
            $s->getAddress()->setPostalCode('69001')->setCity('Lyon')->setCountry('FR');
            $manager->persist($s);
            $suppliers[] = $s;
        }

        // ── Produits ──────────────────────────────────────────────────────────
        $productsData = [
            ['SERV-001', 'Développement web (heure)',       'SERVICE', '90.00',  'H',  '20.00'],
            ['SERV-002', 'Conseil stratégique (journée)',    'SERVICE', '800.00', 'J',  '20.00'],
            ['SERV-003', 'Formation (demi-journée)',         'SERVICE', '400.00', 'J',  '20.00'],
            ['SERV-004', 'Audit et diagnostic',              'SERVICE', '1500.00','U',  '20.00'],
            ['SERV-005', 'Maintenance mensuelle',            'SERVICE', '200.00', 'U',  '20.00'],
            ['PROD-001', 'Licence logicielle (annuelle)',    'PRODUCT', '299.00', 'U',  '20.00'],
            ['PROD-002', 'Abonnement SaaS (mensuel)',        'PRODUCT', '49.00',  'U',  '20.00'],
            ['PROD-003', 'Matériel informatique',            'PRODUCT', '850.00', 'U',  '20.00'],
            ['PROD-004', 'Fournitures de bureau',            'PRODUCT', '45.00',  'U',  '20.00'],
            ['SERV-006', 'Rédaction de contenu (page)',      'SERVICE', '120.00', 'U',  '20.00'],
            ['SERV-007', 'Design graphique (heure)',         'SERVICE', '75.00',  'H',  '20.00'],
            ['SERV-008', 'Hébergement serveur (mois)',       'SERVICE', '25.00',  'U',  '20.00'],
            ['PROD-005', 'Livres et documentation',          'PRODUCT', '35.00',  'U',   '5.50'],
            ['SERV-009', 'Transport et déplacement',         'SERVICE', '0.63',   'KM', '20.00'],
            ['SERV-010', 'Support technique (heure)',        'SERVICE', '65.00',  'H',  '20.00'],
        ];

        $products = [];
        foreach ($productsData as [$ref, $label, $type, $price, $unit, $tva]) {
            $p = new Product();
            $p->setTenant($tenant);
            $p->setReference($ref);
            $p->setLabel($label);
            $p->setType(ProductType::from($type));
            $p->setUnitPrice($price);
            $p->setUnit($unit);
            $p->setTvaRate($tva);
            $manager->persist($p);
            $products[] = $p;
        }

        $manager->flush();

        // ── Factures ──────────────────────────────────────────────────────────
        $statuses = [
            InvoiceStatus::DRAFT, InvoiceStatus::DRAFT,
            InvoiceStatus::VALIDATED,
            InvoiceStatus::SENT, InvoiceStatus::SENT,
            InvoiceStatus::ACKNOWLEDGED, InvoiceStatus::ACKNOWLEDGED, InvoiceStatus::ACKNOWLEDGED,
            InvoiceStatus::PAID, InvoiceStatus::PAID, InvoiceStatus::PAID, InvoiceStatus::PAID,
            InvoiceStatus::REJECTED,
            InvoiceStatus::CANCELLED,
        ];

        $invoices = [];
        foreach ($statuses as $idx => $status) {
            $n      = $idx + 1;
            $inv    = new Invoice();
            $inv->setTenant($tenant);
            $inv->setSequence($sequence);
            $inv->setContact($contacts[$idx % count($contacts)]);
            $inv->setStatus($status);
            $inv->setFormat(InvoiceFormat::FACTURX);
            $inv->setType(InvoiceType::INVOICE);
            $inv->setCurrency('EUR');
            $inv->setSubject("Prestation de services n°$n");
            $inv->setIssueDate(new \DateTimeImmutable('-' . ($idx * 5) . ' days'));
            $inv->setDueDate(new \DateTimeImmutable('-' . ($idx * 5 - 30) . ' days'));

            if ($status !== InvoiceStatus::DRAFT) {
                $inv->setNumber(sprintf('FAC-%d-%04d', date('Y'), $n));
                $inv->setClientNameSnapshot($contacts[$idx % count($contacts)]->getName());
                $inv->setClientSiretSnapshot($contacts[$idx % count($contacts)]->getSiret());
            }

            if ($status === InvoiceStatus::VALIDATED || $status === InvoiceStatus::SENT
                || $status === InvoiceStatus::ACKNOWLEDGED || $status === InvoiceStatus::PAID) {
                $inv->setValidatedAt(new \DateTimeImmutable('-' . ($idx * 5 - 1) . ' days'));
            }

            if ($status === InvoiceStatus::PAID) {
                $inv->setPaidAt(new \DateTimeImmutable('-' . ($idx * 5 - 20) . ' days'));
            }

            // Lignes
            $product = $products[$idx % count($products)];
            $line    = new InvoiceLine();
            $line->setInvoice($inv);
            $line->setProduct($product);
            $line->setPosition(0);
            $line->setDescription($product->getLabel());
            $line->setReference($product->getReference());
            $line->setQuantity((string) rand(1, 5));
            $line->setUnit($product->getUnit());
            $line->setUnitPrice($product->getUnitPrice());
            $line->setDiscount('0.00');
            $line->setTvaRate($product->getTvaRate());

            // Calcul manuel
            $qty     = (float) $line->getQuantity();
            $price   = (float) $line->getUnitPrice();
            $tvaRate = (float) $line->getTvaRate();
            $ht      = round($qty * $price, 2);
            $tva     = round($ht * $tvaRate / 100, 2);
            $line->setAmountHt(number_format($ht, 2, '.', ''));
            $line->setAmountTva(number_format($tva, 2, '.', ''));

            $inv->setTotalHt(number_format($ht, 2, '.', ''));
            $inv->setTotalTva(number_format($tva, 2, '.', ''));
            $inv->setTotalTtc(number_format($ht + $tva, 2, '.', ''));

            if ($status === InvoiceStatus::PAID) {
                $ttc = $ht + $tva;
                $inv->setAmountPaid(number_format($ttc, 2, '.', ''));
            }

            $manager->persist($inv);
            $manager->persist($line);
            $inv->addLine($line);
            $invoices[] = $inv;
        }

        $manager->flush();

        // ── Quelques paiements ────────────────────────────────────────────────
        foreach ($invoices as $inv) {
            if ($inv->getStatus() !== InvoiceStatus::PAID) {
                continue;
            }

            $payment = new Payment();
            $payment->setTenant($tenant);
            $payment->setInvoice($inv);
            $payment->setRecordedBy($accountant);
            $payment->setDirection(PaymentDirection::INCOMING);
            $payment->setAmount($inv->getTotalTtc());
            $payment->setCurrency('EUR');
            $payment->setDate($inv->getPaidAt() ?? new \DateTimeImmutable());
            $payment->setMode(PaymentMode::VIREMENT);
            $payment->setIdempotencyKey(uniqid('fix_', true));
            $manager->persist($payment);
        }

        // ── Factures reçues ───────────────────────────────────────────────────
        foreach (array_slice($suppliers, 0, 3) as $si => $supplier) {
            $ri = new ReceivedInvoice();
            $ri->setTenant($tenant);
            $ri->setSupplierContact($supplier);
            $ri->setSupplierName($supplier->getName());
            $ri->setSupplierSiret($supplier->getSiret());
            $ri->setStatus(
                $si === 0 ? ReceivedInvoiceStatus::PENDING_VALIDATION
                : ($si === 1 ? ReceivedInvoiceStatus::APPROVED
                : ReceivedInvoiceStatus::PAID)
            );
            $ri->setInvoiceNumber('FOURN-2026-' . str_pad((string)($si + 1), 4, '0', STR_PAD_LEFT));
            $ri->setInvoiceDate(new \DateTimeImmutable('-' . ($si * 15 + 5) . ' days'));
            $ri->setReceivedAt(new \DateTimeImmutable('-' . ($si * 15 + 3) . ' days'));
            $ri->setAmountHt(number_format(rand(100, 2000) + 0.0, 2, '.', ''));
            $ri->setAmountTva(number_format((float) $ri->getAmountHt() * 0.20, 2, '.', ''));
            $ri->setAmountTtc(number_format((float) $ri->getAmountHt() * 1.20, 2, '.', ''));
            $ri->setCurrency('EUR');
            $ri->setFormat(InvoiceFormat::FACTURX);
            $manager->persist($ri);
        }

        $manager->flush();
    }
}
