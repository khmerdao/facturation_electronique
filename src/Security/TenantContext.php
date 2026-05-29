<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;

/**
 * Service de contexte tenant — stocke le tenant actif pour la durée d'une requête.
 *
 * Injecté dans tous les services qui ont besoin du tenant courant sans passer
 * par la session à chaque fois. Alimenté par TenantFilterSubscriber au début
 * de chaque requête authentifiée.
 *
 * Exemple d'injection :
 *   public function __construct(private readonly TenantContext $tenantContext) {}
 *   $tenant = $this->tenantContext->requireTenant();
 */
final class TenantContext
{
    private ?Tenant $tenant = null;
    private ?TenantMembership $membership = null;
    private ?User $user = null;

    /**
     * Initialise le contexte après l'authentification.
     * Appelé par TenantFilterSubscriber::onKernelRequest().
     */
    public function setContext(Tenant $tenant, TenantMembership $membership, User $user): void
    {
        $this->tenant = $tenant;
        $this->membership = $membership;
        $this->user = $user;
    }

    /**
     * Réinitialise le contexte (appel after response ou en test).
     */
    public function clear(): void
    {
        $this->tenant = null;
        $this->membership = null;
        $this->user = null;
    }

    /**
     * Retourne le tenant actif ou null si la requête n'est pas authentifiée.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Retourne le tenant actif ou lève une exception si absent.
     * À utiliser dans les services qui ne doivent s'exécuter que dans un
     * contexte tenant (pas les routes publiques ni le super-admin).
     *
     * @throws \LogicException si le contexte n'est pas initialisé
     */
    public function requireTenant(): Tenant
    {
        if (null === $this->tenant) {
            throw new \LogicException('TenantContext is not initialized. Did you call setContext()?');
        }

        return $this->tenant;
    }

    /**
     * Retourne le membership de l'utilisateur courant dans le tenant actif.
     */
    public function getMembership(): ?TenantMembership
    {
        return $this->membership;
    }

    /**
     * Retourne l'utilisateur courant.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Vérifie si le contexte tenant est initialisé.
     */
    public function isInitialized(): bool
    {
        return null !== $this->tenant;
    }
}
