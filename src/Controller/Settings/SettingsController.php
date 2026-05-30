<?php
declare(strict_types=1);
namespace App\Controller\Settings;

use App\Entity\Embeddable\PdpConfig;
use App\Entity\Enum\PdpMode;
use App\Entity\Enum\Role;
use App\Entity\TenantInvitation;
use App\Repository\TenantMembershipRepository;
use App\Repository\InvoiceSequenceRepository;
use App\Repository\InvoiceTemplateRepository;
use App\Repository\ApiKeyRepository;
use App\Repository\WebhookEndpointRepository;
use App\Security\TenantContext;
use App\Service\Invoice\InvoiceNumberingService;
use App\Service\PDP\PdpConfigEncryptorService;
use App\Service\PDP\PdpDispatchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings', name: 'app_settings_')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly InvoiceTemplateRepository $templateRepository,
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly WebhookEndpointRepository $webhookRepository,
        private readonly InvoiceNumberingService $numberingService,
        private readonly PdpConfigEncryptorService $encryptor,
        private readonly PdpDispatchService $pdpDispatch,
    ) {}

    // ── Organisation ────────────────────────────────────────────────────────

    #[Route("/organisation", name: "organisation", methods: ["GET", "POST"])]
    public function organisation(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        if ($request->isMethod("POST")) {
            $d = $request->request->all();
            $tenant->setName($d["name"] ?? $tenant->getName());
            $tenant->setSiret(!empty($d["siret"]) ? $d["siret"] : null);
            $tenant->setTvaIntra(!empty($d["tva_intra"]) ? $d["tva_intra"] : null);
            $tenant->setBillingEmail(!empty($d["billing_email"]) ? $d["billing_email"] : null);
            $tenant->setPhone(!empty($d["phone"]) ? $d["phone"] : null);
            $tenant->setIban(!empty($d["iban"]) ? $d["iban"] : null);
            $tenant->setBic(!empty($d["bic"]) ? $d["bic"] : null);
            $tenant->setWebsite(!empty($d["website"]) ? $d["website"] : null);
            // Adresse
            $addr = $tenant->getAddress();
            $addr->setLine1($d["addr_line1"] ?? null);
            $addr->setLine2($d["addr_line2"] ?? null);
            $addr->setPostalCode($d["addr_postal_code"] ?? null);
            $addr->setCity($d["addr_city"] ?? null);
            $addr->setCountry($d["addr_country"] ?? "FR");
            $this->em->flush();
            $this->addFlash("success", "Organisation mise à jour.");
            return $this->redirectToRoute("app_settings_organisation");
        }

        return $this->render("settings/organisation.html.twig", ["tenant" => $tenant]);
    }

    // ── Utilisateurs ─────────────────────────────────────────────────────────

    #[Route("/users", name: "users", methods: ["GET", "POST"])]
    public function users(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $memberships = $this->membershipRepository->findTenantsForUser(
            $this->tenantContext->getUser()
        );
        // On récupère tous les membres du tenant courant
        $members = array_filter($memberships, fn($m) => (string)$m->getTenant()->getId() === (string)$tenant->getId());

        return $this->render("settings/users.html.twig", [
            "tenant"  => $tenant,
            "members" => array_values($members),
            "roles"   => Role::cases(),
        ]);
    }

    // ── Templates PDF ────────────────────────────────────────────────────────

    #[Route("/templates", name: "templates", methods: ["GET"])]
    public function templates(): Response
    {
        $tenant    = $this->tenantContext->requireTenant();
        $templates = $this->templateRepository->findByTenant($tenant);
        return $this->render("settings/templates.html.twig", [
            "tenant"    => $tenant,
            "templates" => $templates,
        ]);
    }

    // ── Séquences ────────────────────────────────────────────────────────────

    #[Route("/sequences", name: "sequences", methods: ["GET", "POST"])]
    public function sequences(Request $request): Response
    {
        $tenant    = $this->tenantContext->requireTenant();
        $sequences = $this->sequenceRepository->findByTenant($tenant);

        if ($request->isMethod("POST")) {
            $action = $request->request->get("action");
            if ($action === "create") {
                $forCreditNote = $request->request->getBoolean("is_credit_note_sequence");
                $seq = $this->numberingService->createDefaultSequence($tenant, $forCreditNote);
                $seq->setName($request->request->get("name", "Nouvelle séquence"));
                $seq->setPrefix($request->request->get("prefix", "FAC"));
                $this->em->flush();
                $this->addFlash("success", "Séquence créée.");
            }
            return $this->redirectToRoute("app_settings_sequences");
        }

        return $this->render("settings/sequences.html.twig", [
            "tenant"    => $tenant,
            "sequences" => $sequences,
        ]);
    }

    // ── PDP ──────────────────────────────────────────────────────────────────

    #[Route("/pdp", name: "pdp", methods: ["GET", "POST"])]
    public function pdp(Request $request): Response
    {
        $tenant    = $this->tenantContext->requireTenant();
        $pdpConfig = $tenant->getPdpConfig();
        $testResult = null;

        if ($request->isMethod("POST")) {
            $action = $request->request->get("action", "save");
            $d      = $request->request->all();

            $mode = PdpMode::tryFrom($d["mode"] ?? "");
            if ($mode) {
                $pdpConfig->setMode($mode);
            }
            $pdpConfig->setPdpName(!empty($d["pdp_name"]) ? $d["pdp_name"] : null);
            $pdpConfig->setEndpointUrl(!empty($d["endpoint_url"]) ? $d["endpoint_url"] : null);
            $pdpConfig->setEmitterId(!empty($d["emitter_id"]) ? $d["emitter_id"] : null);

            // Ne chiffrer la clé API que si une nouvelle valeur est fournie
            if (!empty($d["api_key"])) {
                $pdpConfig->setApiKeyEncrypted($this->encryptor->encrypt($d["api_key"]));
            }

            if ($action === "test" && $pdpConfig->getEndpointUrl() && $pdpConfig->getApiKeyEncrypted()) {
                $testResult = $this->pdpDispatch->testConnection(
                    $pdpConfig->getEndpointUrl(),
                    $pdpConfig->getApiKeyEncrypted(),
                    $pdpConfig->getEmitterId(),
                );
                $pdpConfig->setLastTestStatus($testResult["success"] ? "OK" : "FAIL");
            }

            $this->em->flush();

            if ($action === "save") {
                $this->addFlash("success", "Configuration PDP sauvegardée.");
                return $this->redirectToRoute("app_settings_pdp");
            }
        }

        return $this->render("settings/pdp.html.twig", [
            "tenant"     => $tenant,
            "pdpConfig"  => $pdpConfig,
            "modes"      => PdpMode::cases(),
            "testResult" => $testResult,
        ]);
    }

    // ── Intégrations (API Keys + Webhooks) ───────────────────────────────────

    #[Route("/integrations", name: "integrations", methods: ["GET"])]
    public function integrations(): Response
    {
        $tenant   = $this->tenantContext->requireTenant();
        $apiKeys  = $this->apiKeyRepository->findActiveByTenant($tenant);
        $webhooks = $this->webhookRepository->findByTenant($tenant);

        return $this->render("settings/integrations.html.twig", [
            "tenant"   => $tenant,
            "apiKeys"  => $apiKeys,
            "webhooks" => $webhooks,
        ]);
    }
}
