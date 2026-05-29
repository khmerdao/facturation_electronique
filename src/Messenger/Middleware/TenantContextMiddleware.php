<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Messenger\Stamp\TenantStamp;
use App\Repository\TenantMembershipRepository;
use App\Repository\TenantRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware Messenger : rehydrate le TenantContext dans les workers.
 *
 * Les workers tournent hors contexte HTTP. Ce middleware lit le TenantStamp
 * (ajouté au dispatch par les services métier) et reconstruit le TenantContext
 * + active le TenantFilter Doctrine avant l'exécution du handler.
 *
 * Sans ce middleware, tous les handlers qui appellent TenantContext::requireTenant()
 * lèveraient une LogicException.
 */
final class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRepository $tenantRepository,
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Uniquement dans les workers (message reçu de la queue)
        if (!$envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        /** @var TenantStamp|null $stamp */
        $stamp = $envelope->last(TenantStamp::class);

        if ($stamp) {
            $this->hydrateContext($stamp->getTenantId(), $stamp->getUserId());
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Toujours nettoyer après le handler (isolation entre messages)
            $this->tenantContext->clear();
            $this->disableTenantFilter();
        }
    }

    private function hydrateContext(string $tenantId, ?string $userId): void
    {
        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            return;
        }

        $user = null;
        $membership = null;

        if ($userId) {
            // Chercher le membership de l'utilisateur émetteur du message
            $memberships = $this->membershipRepository->findActiveMemberships($tenant);
            foreach ($memberships as $m) {
                if ((string) $m->getUser()?->getId() === $userId) {
                    $user = $m->getUser();
                    $membership = $m;
                    break;
                }
            }
        }

        if ($user && $membership) {
            $this->tenantContext->setContext($tenant, $membership, $user);
        }

        // Activer le TenantFilter Doctrine dans le worker
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant_id', (string) $tenant->getId(), 'string');
    }

    private function disableTenantFilter(): void
    {
        if ($this->em->getFilters()->isEnabled('tenant_filter')) {
            $this->em->getFilters()->disable('tenant_filter');
        }
    }
}
