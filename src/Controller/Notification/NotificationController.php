<?php
declare(strict_types=1);
namespace App\Controller\Notification;

use App\Repository\NotificationRepository;
use App\Security\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications', name: 'app_notifications_')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $user   = $this->getUser();
        $filter = $request->query->get('filter', 'all'); // all | unread

        $notifications = $this->notificationRepository->findForUser(
            $user,
            $tenant,
            $filter === 'unread',
            50,
        );

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
            'filter'        => $filter,
            'unreadCount'   => $this->notificationRepository->countUnread($user, $tenant),
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function markRead(string $id): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $notif  = $this->notificationRepository->find($id);

        if ($notif && (string) $notif->getTenant()->getId() === (string) $tenant->getId()) {
            $notif->setReadAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function markAllRead(): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $this->notificationRepository->markAllAsRead($this->getUser(), $tenant);
        $this->addFlash('success', 'Toutes les notifications marquées comme lues.');
        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/api/count', name: 'api_count', methods: ['GET'])]
    public function count(): Response
    {
        $tenant = $this->tenantContext->requireTenant();
        $count  = $this->notificationRepository->countUnread($tenant, $this->getUser());
        return $this->json(['count' => $count]);
    }
}
