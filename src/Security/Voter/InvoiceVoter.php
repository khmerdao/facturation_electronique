<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\Role;
use App\Security\TenantContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour les factures émises.
 *
 * Attributs supportés :
 *   INVOICE_VIEW      — tous les rôles
 *   INVOICE_CREATE    — ACCOUNTANT+
 *   INVOICE_EDIT      — ACCOUNTANT+ ET facture en DRAFT
 *   INVOICE_VALIDATE  — ACCOUNTANT+
 *   INVOICE_SEND      — ACCOUNTANT+
 *   INVOICE_DELETE    — ADMIN+ ET facture en DRAFT
 *   INVOICE_DOWNLOAD  — tous les rôles
 *   INVOICE_DUPLICATE — ACCOUNTANT+
 *   INVOICE_CREDIT_NOTE — ACCOUNTANT+ ET facture ACKNOWLEDGED|PAID
 */
final class InvoiceVoter extends Voter
{
    public const VIEW        = 'INVOICE_VIEW';
    public const CREATE      = 'INVOICE_CREATE';
    public const EDIT        = 'INVOICE_EDIT';
    public const VALIDATE    = 'INVOICE_VALIDATE';
    public const SEND        = 'INVOICE_SEND';
    public const DELETE      = 'INVOICE_DELETE';
    public const DOWNLOAD    = 'INVOICE_DOWNLOAD';
    public const DUPLICATE   = 'INVOICE_DUPLICATE';
    public const CREDIT_NOTE = 'INVOICE_CREDIT_NOTE';

    private const SUPPORTED = [
        self::VIEW, self::CREATE, self::EDIT, self::VALIDATE,
        self::SEND, self::DELETE, self::DOWNLOAD, self::DUPLICATE,
        self::CREDIT_NOTE,
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, self::SUPPORTED, true)) {
            return false;
        }

        // CREATE ne nécessite pas de sujet
        if ($attribute === self::CREATE) {
            return true;
        }

        return $subject instanceof Invoice;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->tenantContext->getMembership();
        if (!$membership) {
            return false;
        }

        $role = $membership->getRole();

        // Super-admin : accès total
        if ($user->isSuperAdmin()) {
            return true;
        }

        /** @var Invoice|null $invoice */
        $invoice = $subject;

        return match ($attribute) {
            self::VIEW, self::DOWNLOAD => true, // tous les rôles
            self::CREATE               => $this->isAtLeast($role, Role::ACCOUNTANT),
            self::DUPLICATE            => $this->isAtLeast($role, Role::ACCOUNTANT),
            self::VALIDATE             => $this->isAtLeast($role, Role::ACCOUNTANT),
            self::SEND                 => $this->isAtLeast($role, Role::ACCOUNTANT),
            self::EDIT                 => $this->isAtLeast($role, Role::ACCOUNTANT)
                                          && $invoice?->getStatus() === InvoiceStatus::DRAFT,
            self::DELETE               => $this->isAtLeast($role, Role::ADMIN)
                                          && $invoice?->getStatus() === InvoiceStatus::DRAFT,
            self::CREDIT_NOTE          => $this->isAtLeast($role, Role::ACCOUNTANT)
                                          && $invoice?->getStatus()->canIssueCreditNote(),
            default                    => false,
        };
    }

    /**
     * Vérifie que le rôle atteint au moins le niveau requis
     * selon la hiérarchie VIEWER < ACCOUNTANT < ADMIN < OWNER.
     */
    private function isAtLeast(Role $actual, Role $required): bool
    {
        return $actual->level() >= $required->level();
    }
}
