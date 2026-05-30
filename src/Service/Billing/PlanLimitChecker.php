<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Tenant;
use App\Entity\Enum\Plan;
use App\Repository\InvoiceRepository;
use App\Repository\TenantMembershipRepository;

/**
 * Vérifie si un tenant peut effectuer une action selon son plan.
 *
 * Utilisé comme garde-fou avant les actions critiques :
 *  - Créer une facture (limite mensuelle)
 *  - Inviter un utilisateur (limite membres)
 *
 * Retourne un objet LimitCheckResult avec :
 *  - allowed : bool
 *  - reason  : string (message d'erreur si non autorisé)
 *  - current : int (usage actuel)
 *  - limit   : int|null (limite du plan, null = illimité)
 */
final class PlanLimitChecker
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly TenantMembershipRepository $membershipRepository,
    ) {}

    // ── Factures ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si le tenant peut créer une nouvelle facture ce mois-ci.
     */
    public function canCreateInvoice(Tenant $tenant): LimitCheckResult
    {
        $limit = $tenant->getPlan()->monthlyInvoiceLimit();

        if ($limit === null) {
            return LimitCheckResult::allowed(0, null);
        }

        $from    = new \DateTimeImmutable('first day of this month 00:00:00');
        $to      = new \DateTimeImmutable('last day of this month 23:59:59');
        $current = $this->invoiceRepository->countByFilters($tenant, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        if ($current >= $limit) {
            return LimitCheckResult::denied(
                sprintf(
                    'Limite atteinte : votre plan %s autorise %d facture(s) par mois. '
                    . 'Vous en avez créé %d ce mois-ci. Passez au plan supérieur pour continuer.',
                    $tenant->getPlan()->label(),
                    $limit,
                    $current,
                ),
                $current,
                $limit,
            );
        }

        return LimitCheckResult::allowed($current, $limit);
    }

    // ── Utilisateurs ──────────────────────────────────────────────────────────

    /**
     * Vérifie si le tenant peut ajouter un membre supplémentaire.
     */
    public function canAddUser(Tenant $tenant): LimitCheckResult
    {
        $limit = $tenant->getPlan()->userLimit();

        if ($limit === null) {
            return LimitCheckResult::allowed(0, null);
        }

        $memberships = $this->membershipRepository->findActiveMemberships($tenant);
        $current     = count($memberships);

        if ($current >= $limit) {
            return LimitCheckResult::denied(
                sprintf(
                    'Limite atteinte : votre plan %s autorise %d utilisateur(s). '
                    . 'Vous en avez déjà %d. Passez au plan Pro ou Enterprise pour inviter davantage.',
                    $tenant->getPlan()->label(),
                    $limit,
                    $current,
                ),
                $current,
                $limit,
            );
        }

        return LimitCheckResult::allowed($current, $limit);
    }

    // ── Plan ──────────────────────────────────────────────────────────────────

    /**
     * Retourne un récapitulatif de l'usage actuel du tenant.
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $plan = $tenant->getPlan();
        $now  = new \DateTimeImmutable();
        $from = new \DateTimeImmutable('first day of this month 00:00:00');
        $to   = new \DateTimeImmutable('last day of this month 23:59:59');

        $invoiceCount = $this->invoiceRepository->countByFilters(
            $tenant,
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        $memberCount = count($this->membershipRepository->findActiveMemberships($tenant));

        return [
            'plan'     => $plan->value,
            'invoices' => [
                'current' => $invoiceCount,
                'limit'   => $plan->monthlyInvoiceLimit(),
                'percent' => $plan->monthlyInvoiceLimit()
                    ? (int) min(100, round($invoiceCount / $plan->monthlyInvoiceLimit() * 100))
                    : 0,
            ],
            'users' => [
                'current' => $memberCount,
                'limit'   => $plan->userLimit(),
                'percent' => $plan->userLimit()
                    ? (int) min(100, round($memberCount / $plan->userLimit() * 100))
                    : 0,
            ],
            'storage' => [
                'limit_bytes' => $plan->storageLimitBytes(),
            ],
        ];
    }
}
