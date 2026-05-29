<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Doctrine\Filter\TenantFilter;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Repository\TenantRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Subscribes to every request to:
 *   1. Résoudre le tenant actif (session ou header X-Tenant-ID pour l'API).
 *   2. Initialiser TenantContext.
 *   3. Activer TenantFilter sur l'EntityManager (WHERE tenant_id = ...).
 *
 * Sur les routes publiques (/login, /register, /api/auth, /admin/*),
 * le filtre n'est pas activé.
 */
final class TenantFilterSubscriber implements EventSubscriberInterface
{
    /** Routes où le filtre tenant ne doit pas s'appliquer. */
    private const EXCLUDED_PREFIXES = [
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/verify-email',
        '/select-organisation',
        '/2fa',
        '/admin',
        '/api/auth',
        '/_',            // profiler, wdt
    ];

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
        private readonly TenantRepository $tenantRepository,
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité 20 : après le SecurityBundle (priorité 8) mais avant les controllers
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ne pas activer le filtre sur les routes exclues
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            // Correspondance exacte pour '/' afin de ne pas tout exclure
            if ($prefix === '/' && $path === '/') {
                return;
            }
            if ($prefix !== '/' && str_starts_with($path, $prefix)) {
                return;
            }
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Super-admin : pas de filtre tenant (vue cross-tenant)
        if ($user->isSuperAdmin()) {
            return;
        }

        // Résoudre le tenant : en-tête API (X-Tenant-ID) ou session
        $tenantId = $request->headers->get('X-Tenant-ID')
            ?? $request->getSession()->get('_tenant_id');

        if (!$tenantId) {
            // Pas de tenant sélectionné → redirection vers le sélecteur
            $event->setResponse(new RedirectResponse($this->router->generate('app_tenant_select')));
            return;
        }

        // Charger le tenant et vérifier l'appartenance
        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant || $tenant->getDeletedAt()) {
            $request->getSession()->remove('_tenant_id');
            $event->setResponse(new RedirectResponse($this->router->generate('app_tenant_select')));
            return;
        }

        $membership = $this->membershipRepository->findOneByUserAndTenant($user, $tenant);
        if (!$membership || !$membership->isActive()) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_tenant_select')));
            return;
        }

        // Initialiser le contexte tenant
        $this->tenantContext->setContext($tenant, $membership, $user);

        // Activer le TenantFilter Doctrine avec l'ID du tenant courant
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant_id', (string) $tenant->getId(), 'string');
    }
}
