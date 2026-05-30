<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sélection du tenant actif.
 *
 * Affiché quand :
 *  - l'utilisateur n'a pas de tenant en session (_tenant_id absent)
 *  - le tenant en session n'existe plus ou a été supprimé
 *  - l'utilisateur appartient à plusieurs organisations
 *
 * Un utilisateur qui n'appartient à aucun tenant est redirigé
 * vers /register pour créer sa première organisation.
 */
final class TenantSelectorController extends AbstractController
{
    public function __construct(
        private readonly TenantMembershipRepository $membershipRepository,
        private readonly Security $security,
    ) {}

    #[Route('/select-organisation', name: 'app_tenant_select', methods: ['GET', 'POST'])]
    public function select(Request $request): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $memberships = $this->membershipRepository->findTenantsForUser($user);

        // Aucune organisation → rediriger vers la création
        if (empty($memberships)) {
            return $this->redirectToRoute('app_register');
        }

        // Une seule organisation → sélection automatique
        if (count($memberships) === 1) {
            return $this->activateTenant($request, (string) $memberships[0]->getTenant()?->getId());
        }

        // POST : l'utilisateur a choisi un tenant dans la liste
        if ($request->isMethod('POST')) {

        // ── Vérification CSRF ─────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('tenant_select', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
            $tenantId = $request->request->get('tenant_id', '');
            // Vérifier que l'utilisateur appartient bien au tenant choisi
            foreach ($memberships as $membership) {
                if ((string) $membership->getTenant()?->getId() === $tenantId) {
                    return $this->activateTenant($request, $tenantId);
                }
            }

            $this->addFlash('error', 'Organisation invalide.');
        }

        return $this->render('auth/tenant_select.html.twig', [
            'memberships' => $memberships,
        ]);
    }

    private function activateTenant(Request $request, string $tenantId): Response
    {
        $request->getSession()->set('_tenant_id', $tenantId);

        return $this->redirectToRoute('app_dashboard');
    }
}
