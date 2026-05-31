<?php

declare(strict_types=1);

namespace App\Controller\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Enum\InvoiceFormat;
use App\Entity\Enum\InvoiceStatus;
use App\Messenger\Message\GenerateInvoicePdfMessage;
use App\Messenger\Message\SendInvoiceToPdpMessage;
use App\Repository\ContactRepository;
use App\Repository\InvoiceRepository;
use App\Repository\InvoiceSequenceRepository;
use App\Repository\InvoiceTemplateRepository;
use App\Repository\ProductRepository;
use App\Security\TenantContext;
use App\Security\Voter\InvoiceVoter;
use App\Service\Invoice\InvoiceCalculatorService;
use App\Service\Invoice\InvoiceDuplicateService;
use App\Service\Invoice\InvoiceStatusService;
use App\Service\Archive\S3StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Turbo\TurboBundle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoices', name: 'app_invoices_')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ContactRepository $contactRepository,
        private readonly ProductRepository $productRepository,
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly InvoiceTemplateRepository $templateRepository,
        private readonly InvoiceCalculatorService $calculator,
        private readonly InvoiceStatusService $statusService,
        private readonly InvoiceDuplicateService $duplicateService,
        private readonly S3StorageService $s3,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant  = $this->tenantContext->requireTenant();
        $filters = [
            'status'     => $request->query->get('status'),
            'contact_id' => $request->query->get('contact_id'),
            'from'       => $request->query->get('from'),
            'to'         => $request->query->get('to'),
            'search'     => $request->query->get('q'),
            'type'       => $request->query->get('type', 'INVOICE'),
        ];
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        return $this->render('invoices/index.html.twig', [
            'invoices' => $this->invoiceRepository->findByFilters($tenant, $filters, $limit, $offset),
            'filters'  => $filters,
            'page'     => $page,
            'total'    => $this->invoiceRepository->countByFilters($tenant, $filters),
            'pages'    => (int) ceil($this->invoiceRepository->countByFilters($tenant, $filters) / $limit),
            'statuses' => InvoiceStatus::cases(),
            'contacts' => $this->contactRepository->findClients($tenant),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::CREATE);
        $tenant  = $this->tenantContext->requireTenant();
        $invoice = new Invoice();
        $invoice->setTenant($tenant);

        if ($request->isMethod('POST')) {
            $this->hydrateInvoice($invoice, $request->request->all(), $tenant);
            $this->hydrateLinesFromRequest($invoice, $request);
            $this->calculator->recalculate($invoice);
            $this->em->persist($invoice);
            $this->em->flush();
            $this->addFlash('success', 'Facture créée en brouillon.');
            return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoices/new.html.twig', [
            'invoice'   => $invoice,
            'contacts'  => $this->contactRepository->findClients($tenant),
            'products'  => $this->productRepository->findAllActive($tenant),
            'sequences' => $this->sequenceRepository->findByTenant($tenant),
            'templates' => $this->templateRepository->findByTenant($tenant),
            'formats'   => InvoiceFormat::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW, $invoice);
        return $this->render('invoices/show.html.twig', [
            'invoice'      => $invoice,
            'tvaBreakdown' => $this->calculator->getTvaBreakdown($invoice),
            'remainingDue' => $this->calculator->getRemainingDue($invoice),
            'creditNotes'  => $this->invoiceRepository->findCreditNotes($invoice),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::EDIT, $invoice);
        $tenant = $this->tenantContext->requireTenant();

        if ($request->isMethod('POST')) {
            $this->hydrateInvoice($invoice, $request->request->all(), $tenant);
            foreach ($invoice->getLines() as $line) { $this->em->remove($line); }
            $invoice->getLines()->clear();
            $this->hydrateLinesFromRequest($invoice, $request);
            $this->calculator->recalculate($invoice);
            $this->em->flush();
            $this->addFlash('success', 'Brouillon sauvegardé.');
            return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoices/edit.html.twig', [
            'invoice'   => $invoice,
            'contacts'  => $this->contactRepository->findClients($tenant),
            'products'  => $this->productRepository->findAllActive($tenant),
            'sequences' => $this->sequenceRepository->findByTenant($tenant),
            'templates' => $this->templateRepository->findByTenant($tenant),
            'formats'   => InvoiceFormat::cases(),
        ]);
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VALIDATE, $invoice);
        if (!$this->isCsrfTokenValid('invoice_' . (string) $invoice->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        $this->em->wrapInTransaction(function () use ($invoice) {
            $this->statusService->validate($invoice, $this->getUser());
        });
        $this->bus->dispatch(new GenerateInvoicePdfMessage((string) $invoice->getId()));
        $this->addFlash('success', sprintf('Facture %s validée.', $invoice->getNumber()));

        if ($request->headers->get('Accept') === TurboBundle::STREAM_MEDIA_TYPE) {
            return $this->render('invoices/_status_badge.stream.html.twig', ['invoice' => $invoice],
                new Response(headers: ['Content-Type' => TurboBundle::STREAM_MEDIA_TYPE])
            );
        }

        return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/send', name: 'send', methods: ['POST'])]
    public function send(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::SEND, $invoice);
        if (!$this->isCsrfTokenValid('invoice_' . (string) $invoice->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        $this->bus->dispatch(new SendInvoiceToPdpMessage((string) $invoice->getId()));
        $this->addFlash('success', 'Transmission PDP/PPF démarrée.');

        if ($request->headers->get('Accept') === TurboBundle::STREAM_MEDIA_TYPE) {
            return $this->render('invoices/_status_badge.stream.html.twig', ['invoice' => $invoice],
                new Response(headers: ['Content-Type' => TurboBundle::STREAM_MEDIA_TYPE])
            );
        }

        return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::DOWNLOAD, $invoice);
        if (!$invoice->getPdfS3Key()) {
            $this->addFlash('warning', 'PDF en cours de génération, veuillez patienter.');
            return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
        }
        return $this->redirect($this->s3->presignedUrl('invoices', $invoice->getPdfS3Key()));
    }

    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::DUPLICATE, $invoice);
        if (!$this->isCsrfTokenValid('invoice_' . (string) $invoice->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        $copy = $this->duplicateService->duplicate($invoice, $this->getUser());
        $this->em->flush();
        $this->addFlash('success', 'Facture dupliquée en brouillon.');
        return $this->redirectToRoute('app_invoices_edit', ['id' => $copy->getId()]);
    }

    #[Route('/{id}/credit-note', name: 'credit_note', methods: ['GET', 'POST'])]
    public function creditNote(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::CREDIT_NOTE, $invoice);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('credit_note_' . (string) $invoice->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }
            $cn = $this->duplicateService->createCreditNote($invoice, $this->getUser(), $request->request->get('reason'));
            $this->em->flush();
            $this->addFlash('success', 'Avoir créé en brouillon.');
            return $this->redirectToRoute('app_invoices_edit', ['id' => $cn->getId()]);
        }
        return $this->render('invoices/credit_note.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::DELETE, $invoice);
        if (!$this->isCsrfTokenValid('invoice_' . (string) $invoice->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        $this->statusService->cancel($invoice, $this->getUser(), $request->request->get('reason'));
        $this->em->flush();
        $this->addFlash('success', 'Facture annulée.');
        return $this->redirectToRoute('app_invoices_index');
    }

    private function hydrateInvoice(Invoice $invoice, array $data, $tenant): void
    {
        if (!empty($data['contact_id'])) {
            $c = $this->contactRepository->find($data['contact_id']);
            if ($c && (string)$c->getTenant()->getId() === (string)$tenant->getId()) {
                $invoice->setContact($c);
            }
        }
        if (!empty($data['sequence_id'])) {
            $s = $this->sequenceRepository->find($data['sequence_id']);
            if ($s) $invoice->setSequence($s);
        }
        if (!empty($data['template_id'])) {
            $t = $this->templateRepository->find($data['template_id']);
            if ($t) $invoice->setTemplate($t);
        }
        if (!empty($data['issue_date'])) $invoice->setIssueDate(new \DateTimeImmutable($data['issue_date']));
        $invoice->setDueDate(!empty($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null);
        if (isset($data['format'])) {
            $fmt = InvoiceFormat::tryFrom($data['format']);
            if ($fmt) $invoice->setFormat($fmt);
        }
        $invoice->setCurrency($data['currency'] ?? 'EUR');
        $invoice->setSubject($data['subject'] ?? null);
        $invoice->setClientReference($data['client_reference'] ?? null);
        $invoice->setClientNotes($data['client_notes'] ?? null);
        $invoice->setInternalNotes($data['internal_notes'] ?? null);
    }

    private function hydrateLinesFromRequest(Invoice $invoice, Request $request): void
    {
        $lines = $request->request->all('lines') ?? [];
        foreach ($lines as $i => $ld) {
            // Ignorer les lignes vraiment vides (ni description ni commentaire)
            $isComment = !empty($ld['is_comment']) && $ld['is_comment'] !== '0';
            if (empty($ld['description']) && !$isComment) continue;
            $line = new InvoiceLine();
            $line->setInvoice($invoice);
            $line->setPosition((int)($ld['position'] ?? $i));
            $line->setIsComment($isComment);
            $line->setDescription($ld['description']);
            $line->setReference($ld['reference'] ?? null);
            $line->setQuantity($ld['quantity'] ?? '1');
            $line->setUnit($ld['unit'] ?? 'U');
            $line->setUnitPrice($ld['unit_price'] ?? '0');
            $line->setDiscount($ld['discount'] ?? '0');
            $line->setTvaRate($ld['tva_rate'] ?? '20.00');
            if (!empty($ld['product_id'])) {
                $p = $this->productRepository->find($ld['product_id']);
                if ($p) $line->setProduct($p);
            }
            $invoice->addLine($line);
            $this->em->persist($line);
        }
    }
}
