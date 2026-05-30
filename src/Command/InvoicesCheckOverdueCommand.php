<?php
declare(strict_types=1);
namespace App\Command;

use App\Messenger\Message\CheckOverdueInvoicesMessage;
use App\Repository\TenantRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:invoices:check-overdue',
    description: 'Vérifie les factures en retard et déclenche les relances.',
)]
final class InvoicesCheckOverdueCommand extends Command
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $tenants = $this->tenantRepository->findAllActive();
        $count   = 0;

        foreach ($tenants as $tenant) {
            $this->bus->dispatch(new CheckOverdueInvoicesMessage((string) $tenant->getId()));
            $count++;
        }

        $io->success("Messages envoyés pour $count tenant(s).");
        return Command::SUCCESS;
    }
}
