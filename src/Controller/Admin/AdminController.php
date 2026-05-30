<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Enum\Plan;
use App\Entity\Enum\TenantStatus;
use App\Entity\SuperAdminLog;
use App\Repository\SuperAdminLogRepository;
use App\Repository\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AdminController extends AbstractController
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly SuperAdminLogRepository $superAdminLogRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_tenants');
        }
        return $this->render('admin/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/admin', name: 'admin_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_tenants');
    }

    #[Route('/admin/tenants', name: 'admin_tenants', methods: ['GET'])]
    public function tenants(Request $request): Response
    {
        $q        = $request->query->get('q');
        $plan     = $request->query->get('plan');
        $status   = $request->query->get('status');

        $tenants = $q
            ? $this->tenantRepository->search($q)
            : $this->tenantRepository->findAllWithStats();

        $countByPlan = $this->tenantRepository->countByPlan();

        return $this->render('admin/tenants.html.twig', [
            'tenants'     => $tenants,
            'q'           => $q,
            'plans'       => Plan::cases(),
            'statuses'    => TenantStatus::cases(),
            'countByPlan' => $countByPlan,
        ]);
    }

    #[Route('/admin/tenants/{id}', name: 'admin_tenant_show', methods: ['GET'])]
    public function tenantShow(string $id): Response
    {
        $tenant = $this->tenantRepository->find($id);
        if (!$tenant) {
            throw $this->createNotFoundException();
        }
        $logs = $this->superAdminLogRepository->findByTargetTenant($tenant, 30);

        return $this->render('admin/tenant_show.html.twig', [
            'tenant' => $tenant,
            'logs'   => $logs,
            'plans'  => Plan::cases(),
        ]);
    }

    #[Route('/admin/tenants/{id}/plan', name: 'admin_tenant_plan', methods: ['POST'])]
    public function changePlan(string $id, Request $request): Response
    {
        $tenant  = $this->tenantRepository->find($id);
        if (!$tenant) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('admin_tenant_plan_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
        if (!$tenant) throw $this->createNotFoundException();

        $plan = Plan::tryFrom($request->request->get('plan', ''));
        if ($plan) {
            $tenant->setPlan($plan);
            $this->logSuperAdminAction(
                $tenant,
                'CHANGE_PLAN',
                ['plan' => $plan->value],
                $request,
            );
            $this->em->flush();
            $this->addFlash('success', 'Plan mis à jour : ' . $plan->value);
        }

        return $this->redirectToRoute('admin_tenant_show', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/suspend', name: 'admin_tenant_suspend', methods: ['POST'])]
    public function suspend(string $id, Request $request): Response
    {
        $tenant = $this->tenantRepository->find($id);
        if (!$tenant) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('admin_tenant_plan_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $suspend = $request->request->getBoolean('suspend', true);
        $tenant->setStatus($suspend ? TenantStatus::SUSPENDED : TenantStatus::ACTIVE);
        $this->logSuperAdminAction(
            $tenant,
            $suspend ? 'SUSPEND' : 'REACTIVATE',
            [],
            $request,
        );
        $this->em->flush();

        $this->addFlash('success', $suspend ? 'Tenant suspendu.' : 'Tenant réactivé.');
        return $this->redirectToRoute('admin_tenant_show', ['id' => $id]);
    }

    #[Route('/admin/logs', name: 'admin_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $logs = $this->superAdminLogRepository->findAll(
            action: null,
            page: $page,
            perPage: 50,
        );

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'page' => $page,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────

    private function logSuperAdminAction(
        $tenant,
        string $action,
        array $details,
        Request $request,
    ): void {
        /** @var \App\Entity\User $superAdmin */
        $superAdmin = $this->getUser();

        $log = new SuperAdminLog();
        $log->setSuperAdmin($superAdmin);
        $log->setTargetTenant($tenant);
        $log->setTargetTenantName($tenant->getName());
        $log->setAction($action);
        $log->setDetails($details);
        $log->setIpAddress($request->getClientIp());

        $this->em->persist($log);
    }
}
