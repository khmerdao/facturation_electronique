<?php
declare(strict_types=1);
namespace App\Command;

use App\Messenger\Message\CreateEReportingBatchMessage;
use App\Repository\TenantRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:ereporting:generate-batch',
    description: 'Génère les lots e-reporting pour tous les tenants.',
)]
final class EReportingGenerateBatchCommand extends Command
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('period', InputArgument::OPTIONAL, 'Période (ex: 2026-09)', date('Y-m'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $period  = $input->getArgument('period');
        $tenants = $this->tenantRepository->findAllActive();

        foreach ($tenants as $tenant) {
            $this->bus->dispatch(new CreateEReportingBatchMessage((string) $tenant->getId(), $period));
        }

        $io->success(sprintf('Lots e-reporting %s dispatchés pour %d tenant(s).', $period, count($tenants)));
        return Command::SUCCESS;
    }
}
