<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Super-admin : login dédié et liste des tenants.
 * Stub — sera complété avec les pages /admin/tenants et /admin/logs.
 */
final class AdminController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_tenants');
        }

        return $this->render('admin/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/admin', name: 'admin_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_tenants');
    }

    #[Route('/admin/tenants', name: 'admin_tenants', methods: ['GET'])]
    public function tenants(): Response
    {
        return $this->render('admin/tenants.html.twig');
    }
}
