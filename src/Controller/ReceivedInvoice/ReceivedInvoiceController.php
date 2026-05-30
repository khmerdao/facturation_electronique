<?php

declare(strict_types=1);

namespace App\Controller\ReceivedInvoice;

use App\Entity\ReceivedInvoice;
use App\Entity\Enum\ReceivedInvoiceStatus;
use App\Messenger\Message\SendTechnicalAckMessage;
use App\Repository\ReceivedInvoiceRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/received-invoices', name: 'app_received_invoices_')]
final class ReceivedInvoiceController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ReceivedInvoiceRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant  = $this->tenantContext->requireTenant();
        $filters = [
            'status'   => $request->query->get('status'),
            'supplier' => $request->query->get('supplier'),
            'from'     => $request->query->get('from'),
            'to'       => $request->query->get('to'),
        ];
        $page     = max(1, (int) $request->query->get('page', 1));
        $invoices = $this->repository->findByFilters($tenant, $filters, 25, ($page - 1) * 25);
        $kpis     = $this->repository->getKpis($tenant);

        return $this->render('received_invoices/index.html.twig', [
            'invoices' => $invoices,
            'filters'  => $filters,
            'page'     => $page,
            'kpis'     => $kpis,
            'pendingAck' => count($this->repository->findPendingTechnicalAck(999)),
            'statuses' => ReceivedInvoiceStatus::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(ReceivedInvoice $invoice): Response
    {
        $this->assertBelongsToTenant($invoice);
        return $this->render('received_invoices/show.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(ReceivedInvoice $invoice): Response
    {
        $this->assertBelongsToTenant($invoice);
        if ($invoice->getStatus() !== ReceivedInvoiceStatus::PENDING_VALIDATION) {
            $this->addFlash('error', 'Cette facture ne peut plus être validée.');
            return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
        }
        $invoice->setStatus(ReceivedInvoiceStatus::APPROVED);
        $this->em->flush();
        $this->addFlash('success', 'Facture validée.');
        return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/contest', name: 'contest', methods: ['POST'])]
    public function contest(ReceivedInvoice $invoice, Request $request): Response
    {
        $this->assertBelongsToTenant($invoice);
        $description = $request->request->get('contest_description');
        if (!$description) {
            $this->addFlash('error', 'La description de la contestation est obligatoire.');
            return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
        }
        $invoice->setStatus(ReceivedInvoiceStatus::CONTESTED);
        $invoice->setContestReason($request->request->get('contest_reason', 'OTHER'));
        $invoice->setContestDescription($description);
        $this->em->flush();
        $this->addFlash('success', 'Facture contestée.');
        return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/ack', name: 'ack', methods: ['POST'])]
    public function sendAck(ReceivedInvoice $invoice): Response
    {
        $this->assertBelongsToTenant($invoice);
        if ($invoice->getTechnicalAckSentAt()) {
            $this->addFlash('info', 'Acquittement déjà envoyé.');
            return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
        }
        $this->bus->dispatch(new SendTechnicalAckMessage((string) $invoice->getId()));
        $this->addFlash('success', 'Acquittement technique envoyé.');
        return $this->redirectToRoute('app_received_invoices_show', ['id' => $invoice->getId()]);
    }

    private function assertBelongsToTenant(ReceivedInvoice $invoice): void
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $invoice->getTenant()->getId() !== (string) $tenant->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
