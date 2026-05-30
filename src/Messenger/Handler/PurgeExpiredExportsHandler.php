<?php
declare(strict_types=1);
namespace App\Messenger\Handler;

use App\Messenger\Message\PurgeExpiredExportsMessage;
use App\Repository\ExportJobRepository;
use App\Service\Archive\S3StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeExpiredExportsHandler
{
    public function __construct(
        private readonly ExportJobRepository $exportJobRepository,
        private readonly S3StorageService $s3,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(PurgeExpiredExportsMessage $message): void
    {
        $deleted = $this->exportJobRepository->deleteExpired();
        $this->logger->info('exports.purged', ['count' => $deleted]);
    }
}
