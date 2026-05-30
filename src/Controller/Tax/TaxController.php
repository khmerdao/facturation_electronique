<?php
declare(strict_types=1);
namespace App\Controller\Tax;

use App\Entity\Enum\ExportType;
use App\Repository\ExportJobRepository;
use App\Repository\InvoiceRepository;
use App\Security\TenantContext;
use App\Service\Archive\S3StorageService;
use App\Service\Export\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tax', name: 'app_tax_')]
final class TaxController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ExportJobRepository $exportJobRepository,
        private readonly ExportService $exportService,
        private readonly S3StorageService $s3,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $year   = (int) $request->query->get('year', date('Y'));
        $month  = (int) $request->query->get('month', 0);

        if ($month > 0) {
            $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            $to   = $from->modify('last day of this month');
        } else {
            $from = new \DateTimeImmutable("$year-01-01");
            $to   = new \DateTimeImmutable("$year-12-31");
        }

        $tvaStats = $this->invoiceRepository->getTvaStats($tenant, $from, $to);

        return $this->render('tax/index.html.twig', [
            'tvaStats'     => $tvaStats,
            'year'         => $year,
            'month'        => $month,
            'from'         => $from,
            'to'           => $to,
            'years'        => range(date('Y'), date('Y') - 5),
        ]);
    }

    #[Route('/exports', name: 'exports', methods: ['GET'])]
    public function exports(): Response
    {
        $tenant  = $this->tenantContext->requireTenant();
        $exports = $this->exportJobRepository->findRecentByTenant($tenant, 20);

        return $this->render('tax/exports.html.twig', [
            'exports'      => $exports,
            'exportTypes'  => ExportType::cases(),
        ]);
    }

    #[Route('/exports/new', name: 'export_new', methods: ['POST'])]
    public function exportNew(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tax_export_new', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        $tenant = $this->tenantContext->requireTenant();
        $type   = $request->request->get('type', 'CSV');
        $from   = new \DateTimeImmutable($request->request->get('from', date('Y-01-01')));
        $to     = new \DateTimeImmutable($request->request->get('to', date('Y-m-d')));
        $user   = $this->getUser();

        $job = match ($type) {
            'FEC'   => $this->exportService->requestFec($tenant, $from, $to, $user),
            default => $this->exportService->requestCsv($tenant, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')], $user),
        };

        $this->addFlash('success', 'Export en cours de génération. Vous serez notifié à la fin.');
        return $this->redirectToRoute('app_tax_exports');
    }

    #[Route('/exports/{id}/download', name: 'export_download', methods: ['GET'])]
    public function exportDownload(string $id): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $job    = $this->exportJobRepository->find($id);

        if (!$job || (string) $job->getTenant()->getId() !== (string) $tenant->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$job->getS3Key()) {
            $this->addFlash('warning', 'Export non disponible.');
            return $this->redirectToRoute('app_tax_exports');
        }

        // Presigned URL S3
        return $this->redirect($this->s3->presignedUrl('exports', $job->getS3Key()));
    }
}
