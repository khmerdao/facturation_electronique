<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\AggregateEReportingTransactionsMessage;
use App\Messenger\Message\CreateEReportingBatchMessage;
use App\Repository\EReportingBatchRepository;
use App\Repository\TenantRepository;
use App\Service\EReporting\EReportingAggregatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Crée le lot e-reporting pour un tenant et une période donnés,
 * puis dispatch l'agrégation.
 */
#[AsMessageHandler]
final class CreateEReportingBatchHandler
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly EReportingAggregatorService $aggregator,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CreateEReportingBatchMessage $message): void
    {
        $tenant = $this->tenantRepository->find($message->getTenantId());

        if (!$tenant) {
            $this->logger->warning('ereporting.create.tenant_not_found', [
                'tenant_id' => $message->getTenantId(),
            ]);
            return;
        }

        $batch = $this->aggregator->getOrCreateBatch($tenant, $message->getPeriod());

        // Déclencher l'agrégation immédiatement
        $this->bus->dispatch(new AggregateEReportingTransactionsMessage((string) $batch->getId()));

        $this->logger->info('ereporting.batch.created_and_dispatched', [
            'batch_id' => (string) $batch->getId(),
            'period'   => $message->getPeriod(),
        ]);
    }
}
