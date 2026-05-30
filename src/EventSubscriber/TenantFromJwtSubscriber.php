<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Repository\TenantRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Injecte le TenantContext depuis le claim JWT `tenant_id`.
 *
 * Le token JWT contient :
 *   - email (identité utilisateur — standard LexikJWT)
 *   - tenant_id (claim personnalisé ajouté lors de la génération dans ApiTokenController)
 *   - role (rôle intra-tenant)
 *
 * Cet subscriber est déclenché après la validation du JWT par LexikJWT.
 * Il résout le membership et active le TenantFilter Doctrine.
 *
 * Note : sur les routes API le TenantFilterSubscriber lit X-Tenant-ID.
 * Ce subscriber court-circuite cette logique en injectant directement
 * le tenant depuis le claim JWT — plus sécurisé (le tenant est signé
 * dans le token, pas fourni par le client).
 */
final class TenantFromJwtSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_AUTHENTICATED => 'onJwtAuthenticated',
        ];
    }

    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $payload  = $event->getPayload();
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$tenantId) {
            return; // Pas de claim tenant — le TenantFilterSubscriber prendra le relais
        }

        /** @var User|null $user */
        $user = $event->getToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $tenant = $this->tenantRepository->find($tenantId);

        if (!$tenant) {
            return;
        }

        // Trouver le membership correspondant
        $membership = $this->membershipRepository->findOneBy([
            'tenant' => $tenant,
            'user'   => $user,
        ]);

        if (!$membership) {
            return;
        }

        // Injecter le contexte tenant
        $this->tenantContext->setContext($tenant, $membership, $user);

        // Activer le filtre Doctrine
        if (!$this->em->getFilters()->isEnabled('tenant_filter')) {
            $filter = $this->em->getFilters()->enable('tenant_filter');
            $filter->setParameter('tenant_id', (string) $tenant->getId(), 'string');
        }
    }
}
