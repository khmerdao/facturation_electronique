<?php
declare(strict_types=1);
namespace App\Controller\Dashboard;

use App\Repository\EReportingBatchRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ReceivedInvoiceRepository;
use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard', name: 'app_dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ReceivedInvoiceRepository $receivedInvoiceRepository,
        private readonly EReportingBatchRepository $ereportingBatchRepository,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $now    = new \DateTimeImmutable();
        $from   = $now->modify('first day of this month');
        $to     = $now->modify('last day of this month');

        $kpis           = $this->invoiceRepository->getKpis($tenant, $from, $to);
        $monthlyRevenue = $this->invoiceRepository->getMonthlyRevenue($tenant, 12);
        $overdueInvoices = $this->invoiceRepository->findOverdue($tenant);
        $pendingAck     = $this->receivedInvoiceRepository->findPendingTechnicalAck(10);
        $ereportingDue  = $this->ereportingBatchRepository->findDueSoon(7);
        $recentInvoices = $this->invoiceRepository->findByFilters($tenant, [], 5, 0);

        return $this->render('dashboard/index.html.twig', [
            'tenant'          => $tenant,
            'kpis'            => $kpis,
            'monthlyRevenue'  => $monthlyRevenue,
            'overdueInvoices' => $overdueInvoices,
            'pendingAck'      => $pendingAck,
            'ereportingDue'   => $ereportingDue,
            'recentInvoices'  => $recentInvoices,
        ]);
    }
}
