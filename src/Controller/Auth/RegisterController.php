<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Enum\Role;
use App\Entity\Enum\TenantStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Inscription d'un nouvel utilisateur + création de son organisation.
 * À terme ce controller sera complété avec un FormType dédié et
 * l'envoi d'un email de vérification.
 */
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {

        // ── Vérification CSRF ─────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {{
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }}
            $email        = trim($request->request->get('email', ''));
            $password     = $request->request->get('password', '');
            $firstName    = trim($request->request->get('first_name', ''));
            $lastName     = trim($request->request->get('last_name', ''));
            $orgName      = trim($request->request->get('organisation_name', ''));

            // Validation minimale
            if (!$email || !$password || !$orgName) {
                $error = 'Tous les champs obligatoires doivent être remplis.';
            } elseif ($this->em->getRepository(User::class)->emailExists($email)) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $user->setFirstName($firstName ?: null);
                $user->setLastName($lastName ?: null);
                $user->setEmailVerified(true); // TODO : envoyer un email de vérification

                $tenant = new Tenant();
                $tenant->setName($orgName);
                $tenant->setSlug($this->slugify($orgName) . '-' . substr(uniqid(), -6));
                $tenant->setStatus(TenantStatus::ONBOARDING);

                $membership = new TenantMembership();
                $membership->setUser($user);
                $membership->setTenant($tenant);
                $membership->setRole(Role::OWNER);
                $membership->setJoinedAt(new \DateTimeImmutable());

                $this->em->persist($user);
                $this->em->persist($tenant);
                $this->em->persist($membership);
                $this->em->flush();

                $this->addFlash('success', 'Compte créé. Connectez-vous !');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/register.html.twig', [
            'error' => $error,
        ]);
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? $text;

        return trim($text, '-');
    }
}
