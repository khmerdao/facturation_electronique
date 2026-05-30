<?php

declare(strict_types=1);

namespace App\Service\EReporting;

use App\Entity\EReportingBatch;
use App\Entity\EReportingTransaction;
use App\Entity\Tenant;
use App\Entity\Enum\EReportingPeriodicity;
use App\Entity\Enum\EReportingStatus;
use App\Entity\Enum\DataSource;
use App\Entity\Enum\EReportingTransactionType;
use App\Repository\EReportingBatchRepository;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\InvoiceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Agrège les transactions B2C et internationales dans les lots e-reporting.
 *
 * Règle de base : toute transaction avec un contact sans SIRET (= non B2B)
 * est sujette à l'e-reporting.
 *
 * Périodicité :
 *   - Mensuelle (par défaut) : le mois en cours
 *   - Trimestrielle : le trimestre en cours
 *
 * Format de la période : "YYYY-MM" (mensuel) ou "YYYY-T1" (trimestriel)
 */
final class EReportingAggregatorService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EReportingBatchRepository $batchRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceCalculatorService $calculator,
        private readonly LoggerInterface $ereportingLogger,
    ) {}

    /**
     * Crée ou retrouve le lot e-reporting pour une période donnée.
     *
     * @param string $period Format "YYYY-MM" ou "YYYY-T1/T2/T3/T4"
     */
    public function getOrCreateBatch(Tenant $tenant, string $period): EReportingBatch
    {
        $existing = $this->batchRepository->findByPeriod($tenant, $period);
        if ($existing) {
            return $existing;
        }

        $batch = new EReportingBatch();
        $batch->setTenant($tenant);
        $batch->setPeriod($period);
        $batch->setPeriodicity($this->detectPeriodicity($period));
        $batch->setStatus(EReportingStatus::NOT_STARTED);
        $batch->setDeadline($this->calculateDeadline($period));

        $this->em->persist($batch);
        $this->em->flush();

        $this->ereportingLogger->info('ereporting.batch.created', [
            'tenant_id' => (string) $tenant->getId(),
            'period'    => $period,
        ]);

        return $batch;
    }

    /**
     * Agrège les transactions du mois/trimestre dans le lot.
     * Remplace les transactions existantes (idempotent).
     */
    public function aggregate(EReportingBatch $batch): void
    {
        $tenant    = $batch->getTenant();
        $period    = $batch->getPeriod();
        [$from, $to] = $this->periodToDates($period);

        // Supprimer les transactions existantes (re-calcul complet)
        foreach ($batch->getTransactions() as $tx) {
            $this->em->remove($tx);
        }
        $this->em->flush();

        // Récupérer les factures e-reporting de la période
        $invoices = $this->invoiceRepository->findForEreporting($tenant, $period);

        if (empty($invoices)) {
            $batch->setIsNil(true);
            $batch->setStatus(EReportingStatus::READY);
            $this->em->flush();

            return;
        }

        $batch->setIsNil(false);

        // Agréger par type de transaction
        $byType = [];

        foreach ($invoices as $invoice) {
            $type = $this->detectTransactionType($invoice);
            $key  = $type->value;

            if (!isset($byType[$key])) {
                $byType[$key] = [
                    'type'            => $type,
                    'totalHt'         => '0.00',
                    'totalTva'        => '0.00',
                    'amountHtByRate'  => [],
                    'amountTvaByRate' => [],
                    'count'           => 0,
                    'date'            => $invoice->getIssueDate(),
                ];
            }

            $breakdown = $this->calculator->getTvaBreakdown($invoice);
            foreach ($breakdown as $rate => $amounts) {
                $byType[$key]['amountHtByRate'][$rate]  = bcadd($byType[$key]['amountHtByRate'][$rate]  ?? '0', $amounts['base'], 2);
                $byType[$key]['amountTvaByRate'][$rate] = bcadd($byType[$key]['amountTvaByRate'][$rate] ?? '0', $amounts['tva'], 2);
            }

            $byType[$key]['totalHt']  = bcadd($byType[$key]['totalHt'], $invoice->getTotalHt(), 2);
            $byType[$key]['totalTva'] = bcadd($byType[$key]['totalTva'], $invoice->getTotalTva(), 2);
            $byType[$key]['count']++;
        }

        foreach ($byType as $data) {
            $tx = new EReportingTransaction();
            $tx->setBatch($batch);
            $tx->setType($data['type']);
            $tx->setTransactionDate($from);
            $tx->setTotalHt($data['totalHt']);
            $tx->setTotalTva($data['totalTva']);
            $tx->setAmountHtByRate($data['amountHtByRate']);
            $tx->setAmountTvaByRate($data['amountTvaByRate']);
            $tx->setTransactionCount($data['count']);
            $tx->setSource(\App\Entity\Enum\DataSource::AUTO);

            $batch->addTransaction($tx);
            $this->em->persist($tx);
        }

        $batch->setStatus(EReportingStatus::READY);
        $this->em->flush();

        $this->ereportingLogger->info('ereporting.batch.aggregated', [
            'batch_id'    => (string) $batch->getId(),
            'period'      => $period,
            'tx_count'    => count($byType),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────

    private function detectPeriodicity(string $period): EReportingPeriodicity
    {
        return str_contains($period, '-T')
            ? EReportingPeriodicity::QUARTERLY
            : EReportingPeriodicity::MONTHLY;
    }

    private function detectTransactionType($invoice): EReportingTransactionType
    {
        $contact = $invoice->getContact();

        if (!$contact) {
            return EReportingTransactionType::B2C;
        }

        $country = $contact->getAddress()->getCountry();

        if ($country === 'FR') {
            return EReportingTransactionType::B2C;
        }

        // Vérification UE vs hors-UE (liste simplifiée)
        $euCountries = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','DE','GR',
                        'HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO',
                        'SK','SI','ES','SE'];

        return in_array($country, $euCountries, true)
            ? EReportingTransactionType::INTRACOM
            : EReportingTransactionType::EXPORT;
    }

    private function periodToDates(string $period): array
    {
        if (str_contains($period, '-T')) {
            [$year, $q] = explode('-T', $period);
            $startMonth = ($q - 1) * 3 + 1;
            $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth));
            $to   = $from->modify('+3 months')->modify('-1 day');
        } else {
            $from = new \DateTimeImmutable($period . '-01');
            $to   = $from->modify('last day of this month');
        }

        return [$from, $to];
    }

    private function calculateDeadline(string $period): \DateTimeImmutable
    {
        [$from, $to] = $this->periodToDates($period);

        // Délai légal : le mois suivant la fin de la période
        return $to->modify('+1 month')->modify('last day of this month');
    }
}
