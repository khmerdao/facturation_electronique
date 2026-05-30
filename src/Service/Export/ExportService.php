<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExportJob;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Enum\ExportStatus;
use App\Entity\Enum\ExportType;
use App\Messenger\Message\GenerateExportFecMessage;
use App\Messenger\Message\GenerateExportCsvMessage;
use App\Repository\InvoiceRepository;
use App\Service\Archive\S3StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service de génération d'exports comptables.
 *
 * Les exports lourds (FEC, ZIP) sont générés de façon asynchrone
 * via Symfony Messenger (file "exports").
 *
 * Format FEC (Fichier des Écritures Comptables) :
 *   - Conforme à l'article A47 A-1 CGI
 *   - 18 colonnes obligatoires, pipe-séparé, UTF-8
 *   - Un enregistrement par ligne de facture + TVA
 */
final class ExportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly S3StorageService $s3,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly int $exportRetentionDays,
    ) {}

    /**
     * Lance une demande d'export FEC en arrière-plan.
     */
    public function requestFec(
        Tenant $tenant,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        User $actor,
    ): ExportJob {
        $job = $this->createJob($tenant, $actor, ExportType::FEC, [
            'from' => $from->format('Y-m-d'),
            'to'   => $to->format('Y-m-d'),
        ]);

        $this->bus->dispatch(new GenerateExportFecMessage((string) $job->getId()));

        return $job;
    }

    /**
     * Lance une demande d'export CSV des factures en arrière-plan.
     */
    public function requestCsv(
        Tenant $tenant,
        array $filters,
        User $actor,
    ): ExportJob {
        $job = $this->createJob($tenant, $actor, ExportType::CSV, $filters);

        $this->bus->dispatch(new GenerateExportCsvMessage((string) $job->getId()));

        return $job;
    }

    /**
     * Génère le FEC de façon synchrone (appelé par le handler Messenger).
     * Retourne le contenu du fichier en string.
     *
     * Colonnes FEC (art. A47 A-1 CGI) :
     * JournalCode|JournalLib|EcritureNum|EcritureDate|CompteNum|CompteLib|
     * CompAuxNum|CompAuxLib|PieceRef|PieceDate|EcritureLib|Debit|Credit|
     * EcritureLet|DateLet|ValidDate|Montantdevise|Idevise
     */
    public function generateFecContent(ExportJob $job): string
    {
        $params = $job->getParams();
        $tenant = $job->getTenant();
        $from   = new \DateTimeImmutable($params['from']);
        $to     = new \DateTimeImmutable($params['to']);

        $invoices = $this->invoiceRepository->findForFec($tenant, $from, $to);

        $lines = [];
        // En-tête FEC
        $lines[] = implode('|', [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
            'PieceRef', 'PieceDate', 'EcritureLib',
            'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate',
            'Montantdevise', 'Idevise',
        ]);

        foreach ($invoices as $invoice) {
            $date   = $invoice->getIssueDate()->format('Ymd');
            $num    = $invoice->getNumber() ?? (string) $invoice->getId();
            $client = mb_substr($invoice->getClientNameSnapshot() ?? 'CLIENT', 0, 17);

            // Ligne TVA collectée par taux
            foreach ($invoice->getLines() as $line) {
                if ($line->isComment() || $line->getAmountHt() === '0.00') {
                    continue;
                }

                $tvaRate  = $line->getTvaRate();
                $compteHt = '706000'; // Prestation de services (à adapter selon type)
                $compteTva = '445710'; // TVA collectée

                // Ligne HT — débit client
                $lines[] = implode('|', [
                    'VT', 'Ventes', $num, $date,
                    '411000', 'Clients', $this->sanitizeFec($client), $this->sanitizeFec($client),
                    $num, $date, $this->sanitizeFec(mb_substr($line->getDescription(), 0, 35)),
                    $this->fecAmount($line->getAmountHt()), '0,00',
                    '', '', $date,
                    $this->fecAmount($line->getAmountHt()), $invoice->getCurrency(),
                ]);

                // Ligne HT — crédit produit
                $lines[] = implode('|', [
                    'VT', 'Ventes', $num, $date,
                    $compteHt, 'Prestations', '', '',
                    $num, $date, $this->sanitizeFec(mb_substr($line->getDescription(), 0, 35)),
                    '0,00', $this->fecAmount($line->getAmountHt()),
                    '', '', $date,
                    $this->fecAmount($line->getAmountHt()), $invoice->getCurrency(),
                ]);

                // Ligne TVA
                if (bccomp($line->getAmountTva(), '0', 2) > 0) {
                    $lines[] = implode('|', [
                        'VT', 'Ventes', $num, $date,
                        '411000', 'Clients', $this->sanitizeFec($client), $this->sanitizeFec($client),
                        $num, $date, 'TVA ' . $tvaRate . '%',
                        $this->fecAmount($line->getAmountTva()), '0,00',
                        '', '', $date,
                        $this->fecAmount($line->getAmountTva()), $invoice->getCurrency(),
                    ]);

                    $lines[] = implode('|', [
                        'VT', 'Ventes', $num, $date,
                        $compteTva, 'TVA collectée ' . $tvaRate . '%', '', '',
                        $num, $date, 'TVA ' . $tvaRate . '%',
                        '0,00', $this->fecAmount($line->getAmountTva()),
                        '', '', $date,
                        $this->fecAmount($line->getAmountTva()), $invoice->getCurrency(),
                    ]);
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Génère le CSV des factures.
     */
    public function generateCsvContent(ExportJob $job): string
    {
        $tenant   = $job->getTenant();
        $filters  = $job->getParams();
        $invoices = $this->invoiceRepository->findByFilters($tenant, $filters);

        $lines = [];
        $lines[] = implode(';', [
            'Numéro', 'Type', 'Statut', 'Date émission', 'Échéance',
            'Client', 'SIRET', 'Total HT', 'Total TVA', 'Total TTC',
            'Payé', 'Devise', 'Format',
        ]);

        foreach ($invoices as $invoice) {
            $lines[] = implode(';', [
                $invoice->getNumber() ?? '',
                $invoice->getType()->value,
                $invoice->getStatus()->value,
                $invoice->getIssueDate()->format('d/m/Y'),
                $invoice->getDueDate()?->format('d/m/Y') ?? '',
                '"' . str_replace('"', '""', $invoice->getClientNameSnapshot() ?? '') . '"',
                $invoice->getClientSiretSnapshot() ?? '',
                str_replace('.', ',', $invoice->getTotalHt()),
                str_replace('.', ',', $invoice->getTotalTva()),
                str_replace('.', ',', $invoice->getTotalTtc()),
                str_replace('.', ',', $invoice->getAmountPaid()),
                $invoice->getCurrency(),
                $invoice->getFormat()->value,
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    // ────────────────────────────────────────────────────────────────────────

    private function createJob(Tenant $tenant, User $actor, ExportType $type, array $params): ExportJob
    {
        $job = new ExportJob();
        $job->setTenant($tenant);
        $job->setGeneratedBy($actor);
        $job->setType($type);
        $job->setStatus(ExportStatus::PENDING);
        $job->setParams($params);
        $job->setExpiresAt(new \DateTimeImmutable('+' . $this->exportRetentionDays . ' days'));

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    /** Convertit un montant PHP en format FEC (virgule décimale). */
    private function fecAmount(string $amount): string
    {
        return str_replace('.', ',', $amount);
    }

    /** Nettoie une chaîne pour le FEC (pas de pipe, pas de retour chariot). */
    private function sanitizeFec(string $value): string
    {
        return str_replace(['|', "\n", "\r"], [' ', ' ', ''], $value);
    }
}
