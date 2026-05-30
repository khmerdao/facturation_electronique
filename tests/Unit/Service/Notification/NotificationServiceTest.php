<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\NotificationSeverity;
use App\Messenger\Message\SendNotificationMessage;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests unitaires de NotificationService.
 *
 * Couvre :
 *  - notify() : crée une Notification avec les bons champs
 *  - notify() : persiste en BDD et dispatche SendNotificationMessage
 *  - alert() / warning() / info() / success() : raccourcis avec bon severity
 *  - notify() : supporte le paramètre $user optionnel (notification globale vs individuelle)
 */
final class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $bus;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->em  = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        // dispatch() doit retourner une Envelope
        $this->bus->method('dispatch')->willReturnCallback(
            fn($msg) => new Envelope($msg)
        );

        $this->service = new NotificationService(
            em:  $this->em,
            bus: $this->bus,
        );
    }

    // ── notify() ─────────────────────────────────────────────────────────

    #[Test]
    public function notify_creates_notification_with_correct_fields(): void
    {
        $tenant = $this->makeTenant();
        $user   = new User();
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->notify(
            tenant:      $tenant,
            type:        'invoice.paid',
            title:       'Paiement reçu',
            description: 'La facture FAC-2026-0001 a été réglée.',
            severity:    NotificationSeverity::SUCCESS,
            actionUrl:   '/invoices/123',
            actionLabel: 'Voir la facture',
            user:        $user,
        );

        self::assertSame($tenant,                       $notif->getTenant());
        self::assertSame($user,                         $notif->getUser());
        self::assertSame('invoice.paid',                $notif->getType());
        self::assertSame('Paiement reçu',               $notif->getTitle());
        self::assertSame(NotificationSeverity::SUCCESS, $notif->getSeverity());
        self::assertSame('/invoices/123',               $notif->getActionUrl());
        self::assertSame('Voir la facture',             $notif->getActionLabel());
    }

    #[Test]
    public function notify_persists_notification_in_database(): void
    {
        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->service->notify(
            tenant:      $this->makeTenant(),
            type:        'test',
            title:       'Test',
            description: 'Description test',
        );
    }

    #[Test]
    public function notify_dispatches_send_notification_message(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SendNotificationMessage::class));

        $this->service->notify(
            tenant:      $this->makeTenant(),
            type:        'test',
            title:       'Test',
            description: 'Desc',
        );
    }

    #[Test]
    public function notify_without_user_creates_tenant_wide_notification(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->notify(
            tenant:      $this->makeTenant(),
            type:        'ereporting.due',
            title:       'E-reporting à soumettre',
            description: 'Lot septembre 2026 à soumettre avant le 31/10.',
            // Pas de $user → notification visible par tous les membres du tenant
        );

        self::assertNull($notif->getUser(), 'Sans $user, la notification est globale au tenant');
    }

    // ── Raccourcis de sévérité ────────────────────────────────────────────

    #[Test]
    public function alert_creates_notification_with_danger_severity(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->alert(
            $this->makeTenant(),
            'pdp.rejected',
            'Facture rejetée',
            'La facture FAC-2026-0001 a été rejetée par le PDP.',
        );

        self::assertSame(NotificationSeverity::DANGER, $notif->getSeverity());
    }

    #[Test]
    public function warning_creates_notification_with_warning_severity(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->warning(
            $this->makeTenant(),
            'invoice.overdue',
            'Facture en retard',
            'La facture FAC-2026-0001 est en retard de paiement.',
        );

        self::assertSame(NotificationSeverity::WARNING, $notif->getSeverity());
    }

    #[Test]
    public function info_creates_notification_with_info_severity(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->info(
            $this->makeTenant(),
            'ereporting.submitted',
            'E-reporting soumis',
            'Le lot septembre 2026 a été soumis à la DGFiP.',
        );

        self::assertSame(NotificationSeverity::INFO, $notif->getSeverity());
    }

    #[Test]
    public function success_creates_notification_with_success_severity(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $notif = $this->service->success(
            $this->makeTenant(),
            'invoice.acknowledged',
            'Facture acceptée',
            'La facture FAC-2026-0001 a été acceptée par le destinataire.',
        );

        self::assertSame(NotificationSeverity::SUCCESS, $notif->getSeverity());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeTenant(): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName('Test Corp');
        return $tenant;
    }
}
