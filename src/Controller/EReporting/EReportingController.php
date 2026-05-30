<?php
declare(strict_types=1);
namespace App\Controller\EReporting;

use App\Entity\EReportingBatch;
use App\Entity\Enum\EReportingStatus;
use App\Messenger\Message\AggregateEReportingTransactionsMessage;
use App\Messenger\Message\CreateEReportingBatchMessage;
use App\Repository\EReportingBatchRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/e-reporting', name: 'app_e_reporting_')]
final class EReportingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EReportingBatchRepository $batchRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant  = $this->tenantContext->requireTenant();
        $batches = $this->batchRepository->findByTenant($tenant, 24);
        $late    = $this->batchRepository->findLate();

        return $this->render('e_reporting/index.html.twig', [
            'batches'    => $batches,
            'late'       => $late,
            'statuses'   => EReportingStatus::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(EReportingBatch $batch): Response
    {
        $this->assertSameTenant($batch);
        return $this->render('e_reporting/show.html.twig', ['batch' => $batch]);
    }

    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $period = $request->request->get('period', date('Y-m'));
        $this->bus->dispatch(new CreateEReportingBatchMessage((string) $tenant->getId(), $period));
        $this->addFlash('success', "Lot e-reporting $period en cours de génération.");
        return $this->redirectToRoute('app_e_reporting_index');
    }

    #[Route('/{id}/aggregate', name: 'aggregate', methods: ['POST'])]
    public function aggregate(EReportingBatch $batch): Response
    {
        $this->assertSameTenant($batch);
        $this->bus->dispatch(new AggregateEReportingTransactionsMessage((string) $batch->getId()));
        $this->addFlash('success', 'Recalcul des transactions lancé.');
        return $this->redirectToRoute('app_e_reporting_show', ['id' => $batch->getId()]);
    }

    #[Route('/{id}/submit', name: 'submit', methods: ['POST'])]
    public function submit(EReportingBatch $batch): Response
    {
        $this->assertSameTenant($batch);
        if ($batch->getStatus() !== EReportingStatus::READY) {
            $this->addFlash('error', 'Le lot doit être en statut READY pour être soumis.');
            return $this->redirectToRoute('app_e_reporting_show', ['id' => $batch->getId()]);
        }
        $batch->setStatus(EReportingStatus::SUBMITTED);
        $batch->setSubmittedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->addFlash('success', 'Lot soumis à la DGFiP. En attente de confirmation.');
        return $this->redirectToRoute('app_e_reporting_show', ['id' => $batch->getId()]);
    }

    private function assertSameTenant(EReportingBatch $batch): void
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $batch->getTenant()->getId() !== (string) $tenant->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
