<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Messenger\Message\GenerateInvoicePdfMessage;
use App\Messenger\Message\SendInvoiceToPdpMessage;
use App\Repository\ContactRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Security\TenantContext;
use App\Security\Voter\InvoiceVoter;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST — Factures émises.
 *
 * GET    /api/invoices           — liste paginée
 * POST   /api/invoices           — créer une facture
 * GET    /api/invoices/{id}      — détail
 * PUT    /api/invoices/{id}      — mettre à jour (DRAFT uniquement)
 * POST   /api/invoices/{id}/validate — valider (DRAFT → VALIDATED)
 * POST   /api/invoices/{id}/send     — transmettre au PDP/PPF
 * DELETE /api/invoices/{id}      — annuler (DRAFT uniquement)
 */
#[Route('/api/invoices', name: 'api_invoices_')]
final class ApiInvoiceController extends AbstractApiController
{
    public function __construct(
        TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ContactRepository $contactRepository,
        private readonly ProductRepository $productRepository,
        private readonly InvoiceCalculatorService $calculator,
        private readonly InvoiceStatusService $statusService,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct($tenantContext);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant  = $this->tenantContext->requireTenant();
        $params  = $this->getPaginationParams($request);

        $filters = [
            'status'     => $request->query->get('status'),
            'contact_id' => $request->query->get('contact_id'),
            'from'       => $request->query->get('from'),
            'to'         => $request->query->get('to'),
            'search'     => $request->query->get('q'),
        ];

        $invoices = $this->invoiceRepository->findByFilters(
            $tenant, $filters, $params['perPage'], $params['offset']
        );
        $total    = $this->invoiceRepository->countByFilters($tenant, $filters);

        return $this->paginated(
            array_map($this->serializeInvoice(...), $invoices),
            $total,
            $params['page'],
            $params['perPage'],
            '/api/invoices',
        );
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(Invoice $invoice): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($invoice)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW, $invoice);

        return $this->success($this->serializeInvoice($invoice, true));
    }

    // ── Création ──────────────────────────────────────────────────────────────

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::CREATE);
        $tenant = $this->tenantContext->requireTenant();

        $data = json_decode($request->getContent(), true) ?? [];

        // Validation minimale
        if (empty($data['lines'])) {
            return $this->unprocessable('Au moins une ligne est requise.', [
                ['field' => 'lines', 'message' => 'Le tableau lines ne peut pas être vide.'],
            ]);
        }

        $invoice = new Invoice();
        $invoice->setTenant($tenant);

        $error = $this->hydrateInvoice($invoice, $data);
        if ($error) {
            return $this->unprocessable($error);
        }

        $this->hydrateLinesFromData($invoice, $data['lines'] ?? []);
        $this->calculator->recalculate($invoice);

        $this->em->persist($invoice);
        $this->em->flush();

        return $this->created($this->serializeInvoice($invoice));
    }

    // ── Mise à jour ───────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Invoice $invoice, Request $request): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($invoice)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(InvoiceVoter::EDIT, $invoice);

        $data  = json_decode($request->getContent(), true) ?? [];
        $tenant = $this->tenantContext->requireTenant();

        $error = $this->hydrateInvoice($invoice, $data);
        if ($error) {
            return $this->unprocessable($error);
        }

        if (isset($data['lines'])) {
            foreach ($invoice->getLines() as $line) {
                $this->em->remove($line);
            }
            $invoice->getLines()->clear();
            $this->hydrateLinesFromData($invoice, $data['lines']);
        }

        $this->calculator->recalculate($invoice);
        $this->em->flush();

        return $this->success($this->serializeInvoice($invoice));
    }

    // ── Valider ───────────────────────────────────────────────────────────────

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(Invoice $invoice): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($invoice)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(InvoiceVoter::VALIDATE, $invoice);

        try {
            $this->em->wrapInTransaction(function () use ($invoice) {
                $this->statusService->validate($invoice, $this->getUser());
            });
        } catch (\LogicException $e) {
            return $this->unprocessable($e->getMessage());
        }

        $this->bus->dispatch(new GenerateInvoicePdfMessage((string) $invoice->getId()));

        return $this->success($this->serializeInvoice($invoice));
    }

    // ── Envoyer au PDP ────────────────────────────────────────────────────────

    #[Route('/{id}/send', name: 'send', methods: ['POST'])]
    public function send(Invoice $invoice): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($invoice)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(InvoiceVoter::SEND, $invoice);

        $this->bus->dispatch(new SendInvoiceToPdpMessage((string) $invoice->getId()));

        return $this->success([
            'message'    => 'Transmission au PDP/PPF démarrée.',
            'invoice_id' => (string) $invoice->getId(),
            'status'     => $invoice->getStatus()->value,
        ]);
    }

    // ── Annuler ───────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Invoice $invoice): JsonResponse
    {
        if (!$this->belongsToCurrentTenant($invoice)) {
            return $this->notFound();
        }
        $this->denyAccessUnlessGranted(InvoiceVoter::DELETE, $invoice);

        try {
            $this->statusService->cancel($invoice, $this->getUser(), 'Annulation via API');
            $this->em->flush();
        } catch (\LogicException $e) {
            return $this->unprocessable($e->getMessage());
        }

        return $this->noContent();
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serializeInvoice(Invoice $invoice, bool $withLines = false): array
    {
        $data = [
            'id'             => (string) $invoice->getId(),
            'number'         => $invoice->getNumber(),
            'type'           => $invoice->getType()->value,
            'status'         => $invoice->getStatus()->value,
            'format'         => $invoice->getFormat()->value,
            'currency'       => $invoice->getCurrency(),
            'subject'        => $invoice->getSubject(),
            'issue_date'     => $invoice->getIssueDate()->format('Y-m-d'),
            'due_date'       => $invoice->getDueDate()?->format('Y-m-d'),
            'client'         => [
                'id'    => $invoice->getContact() ? (string) $invoice->getContact()->getId() : null,
                'name'  => $invoice->getClientNameSnapshot() ?? $invoice->getContact()?->getName(),
                'siret' => $invoice->getClientSiretSnapshot() ?? $invoice->getContact()?->getSiret(),
            ],
            'amounts'        => [
                'total_ht'     => $invoice->getTotalHt(),
                'total_tva'    => $invoice->getTotalTva(),
                'total_ttc'    => $invoice->getTotalTtc(),
                'amount_paid'  => $invoice->getAmountPaid(),
                'remaining_due' => $this->calculator->getRemainingDue($invoice),
            ],
            'pdf_url'        => $invoice->getPdfS3Key() ? '/api/invoices/' . $invoice->getId() . '/download' : null,
            'validated_at'   => $invoice->getValidatedAt()?->format(\DateTimeInterface::ATOM),
            'paid_at'        => $invoice->getPaidAt()?->format(\DateTimeInterface::ATOM),
            'created_at'     => $invoice->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at'     => $invoice->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withLines) {
            $data['lines']         = array_map($this->serializeLine(...), $invoice->getLines()->toArray());
            $data['tva_breakdown'] = $this->calculator->getTvaBreakdown($invoice);
        }

        return $data;
    }

    private function serializeLine(InvoiceLine $line): array
    {
        return [
            'id'          => (string) $line->getId(),
            'position'    => $line->getPosition(),
            'is_comment'  => $line->isComment(),
            'reference'   => $line->getReference(),
            'description' => $line->getDescription(),
            'quantity'    => $line->getQuantity(),
            'unit'        => $line->getUnit(),
            'unit_price'  => $line->getUnitPrice(),
            'discount'    => $line->getDiscount(),
            'tva_rate'    => $line->getTvaRate(),
            'amount_ht'   => $line->getAmountHt(),
            'amount_tva'  => $line->getAmountTva(),
        ];
    }

    // ── Hydratation ───────────────────────────────────────────────────────────

    private function hydrateInvoice(Invoice $invoice, array $data): ?string
    {
        $tenant = $this->tenantContext->requireTenant();

        if (!empty($data['contact_id'])) {
            $contact = $this->contactRepository->find($data['contact_id']);
            if (!$contact || (string) $contact->getTenant()->getId() !== (string) $tenant->getId()) {
                return 'Contact introuvable ou non autorisé.';
            }
            $invoice->setContact($contact);
        }

        if (!empty($data['format'])) {
            $fmt = InvoiceFormat::tryFrom($data['format']);
            if (!$fmt) return 'Format invalide. Valeurs acceptées : ' . implode(', ', array_column(InvoiceFormat::cases(), 'value'));
            $invoice->setFormat($fmt);
        }

        if (!empty($data['issue_date'])) {
            try {
                $invoice->setIssueDate(new \DateTimeImmutable($data['issue_date']));
            } catch (\Exception) {
                return 'issue_date invalide. Format attendu : YYYY-MM-DD.';
            }
        }

        if (array_key_exists('due_date', $data)) {
            $invoice->setDueDate($data['due_date'] ? new \DateTimeImmutable($data['due_date']) : null);
        }

        $invoice->setCurrency($data['currency'] ?? $invoice->getCurrency() ?? 'EUR');
        $invoice->setSubject($data['subject'] ?? $invoice->getSubject());
        $invoice->setClientReference($data['client_reference'] ?? $invoice->getClientReference());
        $invoice->setClientNotes($data['client_notes'] ?? $invoice->getClientNotes());
        $invoice->setInternalNotes($data['internal_notes'] ?? $invoice->getInternalNotes());

        return null;
    }

    private function hydrateLinesFromData(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $i => $lineData) {
            if (empty($lineData['description'])) {
                continue;
            }
            $line = new InvoiceLine();
            $line->setInvoice($invoice);
            $line->setPosition((int) ($lineData['position'] ?? $i));
            $line->setIsComment($lineData['is_comment'] ?? false);
            $line->setDescription($lineData['description']);
            $line->setReference($lineData['reference'] ?? null);
            $line->setQuantity((string) ($lineData['quantity'] ?? '1'));
            $line->setUnit($lineData['unit'] ?? 'U');
            $line->setUnitPrice((string) ($lineData['unit_price'] ?? '0'));
            $line->setDiscount((string) ($lineData['discount'] ?? '0'));
            $line->setTvaRate((string) ($lineData['tva_rate'] ?? '20.00'));

            if (!empty($lineData['product_id'])) {
                $product = $this->productRepository->find($lineData['product_id']);
                if ($product) {
                    $line->setProduct($product);
                }
            }

            $invoice->addLine($line);
            $this->em->persist($line);
        }
    }
}
