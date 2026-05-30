<?php
declare(strict_types=1);
namespace App\Command;

use App\Messenger\Message\RefreshSireneStatusMessage;
use App\Repository\ContactRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:sirene:refresh',
    description: 'Rafraîchit les statuts Sirene des contacts actifs.',
)]
final class SireneRefreshCommand extends Command
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $contacts = $this->contactRepository->findDueSireneRefresh(200);

        foreach ($contacts as $contact) {
            $this->bus->dispatch(new RefreshSireneStatusMessage((string) $contact->getId()));
        }

        $io->success(sprintf('%d contacts envoyés en rafraîchissement Sirene.', count($contacts)));
        return Command::SUCCESS;
    }
}
