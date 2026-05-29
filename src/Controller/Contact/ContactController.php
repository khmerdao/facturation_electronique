<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Security\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Clients et fournisseurs.
 * Stub — à compléter avec la logique métier.
 */
#[Route('/contacts', name: 'app_contacts_')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tenant = $this->tenantContext->requireTenant();

        return $this->render('contact/index.html.twig', [
            'tenant' => $tenant,
        ]);
    }
}
