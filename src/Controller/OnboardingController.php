<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Enum\OnboardingStep;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours d'onboarding initial (étape ORGANISATION + PREFERENCES).
 * OnboardingSubscriber redirige ici tant que onboardingCompleted = false.
 * À terme, ce controller sera étoffé avec des FormTypes dédiés.
 */
#[Route('/onboarding', name: 'app_onboarding_')]
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Étape 1 — Informations de l'organisation (SIRET, TVA, logo, PDP).
     */
    #[Route('/organisation', name: 'organisation', methods: ['GET', 'POST'])]
    public function organisation(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        if ($request->isMethod('POST')) {

        // ── Vérification CSRF ────────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('onboarding_form', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
            $name  = trim($request->request->get('name', ''));
            $siret = trim($request->request->get('siret', ''));

            if ($name) {
                $tenant->setName($name);
            }
            if ($siret) {
                $tenant->setSiret($siret);
            }

            $tenant->setOnboardingStep(OnboardingStep::PREFERENCES);
            $this->em->flush();

            return $this->redirectToRoute('app_onboarding_preferences');
        }

        return $this->render('onboarding/organisation.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Étape 2 — Préférences (devise, modèle, séquence de numérotation).
     */
    #[Route('/preferences', name: 'preferences', methods: ['GET', 'POST'])]
    public function preferences(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        if ($request->isMethod('POST')) {

        // ── Vérification CSRF ────────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('onboarding_form', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
            $tenant->setOnboardingCompleted(true);
            $tenant->setOnboardingStep(OnboardingStep::COMPLETED);
            $this->em->flush();

            $this->addFlash('success', 'Votre organisation est configurée. Bienvenue !');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('onboarding/preferences.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
