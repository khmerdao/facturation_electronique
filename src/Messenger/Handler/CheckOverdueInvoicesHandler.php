<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Entity\Enum\NotificationSeverity;
use App\Messenger\Message\CheckOverdueInvoicesMessage;
use App\Messenger\Message\SendRelanceEmailMessage;
use App\Repository\InvoiceRepository;
use App\Repository\TenantRepository;
use App\Service\Notification\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CheckOverdueInvoicesHandler
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly NotificationService $notificationService,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(CheckOverdueInvoicesMessage $message): void
    {
        $tenant          = $this->tenantRepository->find($message->getTenantId());
        if (!$tenant) return;

        $overdueInvoices = $this->invoiceRepository->findOverdue($tenant);

        foreach ($overdueInvoices as $invoice) {
            // Déclencher la première relance email
            $this->bus->dispatch(new SendRelanceEmailMessage(
                (string) $invoice->getId(),
                1,
            ));

            // Créer une notification dashboard
            $this->notificationService->warning(
                $tenant,
                'invoice.overdue',
                'Facture en retard',
                sprintf('La facture %s est en retard de paiement.', $invoice->getNumber() ?? '—'),
            );
        }
    }
}
