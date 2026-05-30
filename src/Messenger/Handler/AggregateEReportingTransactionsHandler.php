<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\AggregateEReportingTransactionsMessage;
use App\Repository\EReportingBatchRepository;
use App\Service\EReporting\EReportingAggregatorService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AggregateEReportingTransactionsHandler
{
    public function __construct(
        private readonly EReportingBatchRepository $batchRepository,
        private readonly EReportingAggregatorService $aggregator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AggregateEReportingTransactionsMessage $message): void
    {
        $batch = $this->batchRepository->find($message->getBatchId());

        if (!$batch) {
            $this->logger->warning('ereporting.aggregate.batch_not_found', [
                'batch_id' => $message->getBatchId(),
            ]);
            return;
        }

        $this->aggregator->aggregate($batch);

        $this->logger->info('ereporting.aggregate.done', [
            'batch_id' => $message->getBatchId(),
            'period'   => $batch->getPeriod(),
            'nil'      => $batch->isNil(),
        ]);
    }
}
