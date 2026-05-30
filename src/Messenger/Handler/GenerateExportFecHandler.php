<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Entity\Enum\ExportStatus;
use App\Messenger\Message\GenerateExportFecMessage;
use App\Repository\ExportJobRepository;
use App\Service\Archive\S3StorageService;
use App\Service\Export\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateExportFecHandler
{
    public function __construct(
        private readonly ExportJobRepository $exportJobRepository,
        private readonly ExportService $exportService,
        private readonly S3StorageService $s3,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateExportFecMessage $message): void
    {
        $job = $this->exportJobRepository->find($message->getExportJobId());
        if (!$job) {
            $this->logger->warning('export.fec.job_not_found', ['job_id' => $message->getExportJobId()]);
            return;
        }

        $job->setStatus(ExportStatus::PROCESSING);
        $this->em->flush();

        try {
            $content  = $this->exportService->generateFecContent($job);
            $filename = sprintf('FEC_%s_%s.txt',
                (string) $job->getTenant()->getId(),
                date('Ymd_His')
            );
            $s3Key = sprintf('tenants/%s/exports/%s', (string) $job->getTenant()->getId(), $filename);

            $this->s3->upload('exports', $s3Key, $content, 'text/plain');

            $job->setStatus(ExportStatus::DONE);
            $job->setS3Key($s3Key);
            $job->setFileHash($this->s3->hashContent($content));
            $job->setFileSize(strlen($content));
            $job->setCompletedAt(new \DateTimeImmutable());

            $this->logger->info('export.fec.done', ['job_id' => $message->getExportJobId(), 's3_key' => $s3Key]);
        } catch (\Throwable $e) {
            $job->setStatus(ExportStatus::ERROR);
            $job->setErrorMessage($e->getMessage());
            $this->logger->error('export.fec.error', ['job_id' => $message->getExportJobId(), 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $this->em->flush();
        }
    }
}
