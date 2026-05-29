<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Redirige automatiquement vers /onboarding si le tenant n'a pas terminé
 * son onboarding. Priorité basse (après TenantFilterSubscriber).
 */
final class OnboardingSubscriber implements EventSubscriberInterface
{
    /** Routes accessibles même si l'onboarding est incomplet. */
    private const ONBOARDING_ALLOWED = [
        '/onboarding',
        '/logout',
        '/settings',
        '/_',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            return;
        }

        if ($tenant->isOnboardingCompleted()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::ONBOARDING_ALLOWED as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return;
            }
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate('app_onboarding_organisation'),
        ));
    }
}
