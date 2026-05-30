<?php
declare(strict_types=1);
namespace App\Command;

use App\Messenger\Message\PurgeExpiredExportsMessage;
use App\Repository\TenantRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:exports:purge-expired',
    description: 'Supprime les exports expirés de S3 et de la base de données.',
)]
final class ExportsPurgeExpiredCommand extends Command
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

        foreach ($tenants as $tenant) {
            $this->bus->dispatch(new PurgeExpiredExportsMessage((string) $tenant->getId()));
        }

        $io->success(sprintf('Purge dispatchée pour %d tenant(s).', count($tenants)));
        return Command::SUCCESS;
    }
}
