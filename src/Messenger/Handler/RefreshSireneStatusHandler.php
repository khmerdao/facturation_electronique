<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\RefreshSireneStatusMessage;
use App\Repository\ContactRepository;
use App\Service\PDP\SireneApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefreshSireneStatusHandler
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly SireneApiClient $sireneClient,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(RefreshSireneStatusMessage $message): void
    {
        $contact = $this->contactRepository->find($message->getContactId());
        if (!$contact || !$contact->getSiret()) return;

        // Vérifie si l'établissement est actif
        $this->sireneClient->isActive($contact->getSiret());
        // Mettre à jour la date de dernière vérification
        $contact->setSireneCheckedAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
