<?php
declare(strict_types=1);
namespace App\Controller\Payment;

use App\Form\PaymentType as PaymentForm;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Enum\PaymentMode;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Security\TenantContext;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payments', name: 'app_payments_')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant   = $this->tenantContext->requireTenant();
        $filters  = [
            'direction' => $request->query->get('direction'),
            'from'      => $request->query->get('from'),
            'to'        => $request->query->get('to'),
        ];
        $page     = max(1, (int)$request->query->get('page', 1));
        $payments = $this->paymentRepository->findByTenant($tenant, $filters, 25, ($page - 1) * 25);

        return $this->render('payments/index.html.twig', [
            'payments' => $payments,
            'filters'  => $filters,
            'page'     => $page,
            'modes'    => PaymentMode::cases(),
        ]);
    }

    #[Route('/invoices/{invoiceId}/payment', name: 'record', methods: ['GET', 'POST'])]
    public function record(Invoice $invoice, Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string)$invoice->getTenant()->getId() !== (string)$tenant->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$invoice->getStatus()->canRecordPayment()) {
            $this->addFlash('error', 'Statut ACKNOWLEDGED requis pour enregistrer un paiement.');
            return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
        }

        $form = $this->createForm(PaymentForm::class, null, ['invoice' => $invoice]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $this->paymentService->recordOnInvoice($invoice, [
                    'amount'    => (string) $data['amount'],
                    'date'      => $data['date'],
                    'mode'      => $data['mode'],
                    'reference' => $data['reference'] ?? null,
                    'currency'  => $invoice->getCurrency(),
                ], $this->getUser());
                $this->addFlash('success', 'Paiement enregistré.');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }
            return $this->redirectToRoute('app_invoices_show', ['id' => $invoice->getId()]);
        }

        return $this->render('payments/record.html.twig', [
            'invoice' => $invoice,
            'form'    => $form,
        ]);
    }


    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Payment $payment): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string)$payment->getTenant()->getId() !== (string)$tenant->getId()) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->paymentService->cancel($payment, $this->getUser());
            $this->addFlash('success', 'Paiement annulé.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        $invoiceId = $payment->getInvoice()?->getId();
        return $invoiceId
            ? $this->redirectToRoute('app_invoices_show', ['id' => $invoiceId])
            : $this->redirectToRoute('app_payments_index');
    }
}
