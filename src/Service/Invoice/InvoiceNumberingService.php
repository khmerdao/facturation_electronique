<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\InvoiceSequence;
use App\Entity\Tenant;
use App\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Service de numérotation des factures.
 *
 * Garantit l'absence de trou dans la séquence (art. 242 nonies A annexe II CGI)
 * grâce à un verrou distribué Redis + transaction DB avec lock pessimiste.
 *
 * Format généré : {PREFIX}{SEP}{YYYY|YY}{SEP}{MM}{SEP}{NNNN}
 * Exemples :
 *   - FAC-2026-0001
 *   - FAC-2026-01-0001  (avec mois)
 *   - AV-26-0001        (avoir, format court)
 */
final class InvoiceNumberingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceSequenceRepository $sequenceRepository,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Alloue le prochain numéro de facture et incrémente la séquence.
     *
     * ⚠️  Doit être appelé dans une transaction Doctrine ouverte.
     * Le verrou Redis est acquis AVANT la transaction pour éviter les deadlocks.
     *
     * @throws \RuntimeException si la séquence est introuvable ou le verrou non acquérable
     */
    public function allocate(InvoiceSequence $sequence): string
    {
        $lockKey = sprintf('invoice_numbering_%s', $sequence->getId());
        $lock    = $this->lockFactory->createLock($lockKey, ttl: 10.0, autoRelease: true);

        if (!$lock->acquire(blocking: true)) {
            throw new \RuntimeException(sprintf(
                'Impossible d\'acquérir le verrou de numérotation pour la séquence "%s".',
                $sequence->getName(),
            ));
        }

        try {
            // Lock pessimiste DB (SELECT FOR UPDATE) : deuxième niveau de protection
            $sequence = $this->sequenceRepository->lockForUpdate($sequence);

            $now         = new \DateTimeImmutable();
            $currentYear = (int) $now->format('Y');

            // Réinitialisation annuelle si configurée et si on change d'année
            if ($sequence->isResetYearly()
                && $sequence->getLastYear() !== null
                && $sequence->getLastYear() !== $currentYear
            ) {
                $sequence->setNextNumber($sequence->getStartNumber());
                $this->logger->info('invoice.sequence.yearly_reset', [
                    'sequence_id' => (string) $sequence->getId(),
                    'year'        => $currentYear,
                ]);
            }

            $number = $sequence->getNextNumber();

            // Construire la chaîne de numéro
            $formatted = $this->format($sequence, $number, $now);

            // Incrémenter + verrouiller la séquence
            $sequence->setNextNumber($number + 1);
            $sequence->setLastYear($currentYear);
            $sequence->setLocked(true);

            $this->em->flush();

            $this->logger->info('invoice.number.allocated', [
                'number'      => $formatted,
                'sequence_id' => (string) $sequence->getId(),
            ]);

            return $formatted;
        } finally {
            $lock->release();
        }
    }

    /**
     * Prévisualise le prochain numéro sans modifier la séquence.
     * Utilisé sur /settings/sequences pour montrer l'aperçu en temps réel.
     */
    public function preview(InvoiceSequence $sequence): string
    {
        return $this->format($sequence, $sequence->getNextNumber(), new \DateTimeImmutable());
    }

    /**
     * Crée une séquence par défaut pour un nouveau tenant.
     * Appelé lors de l'onboarding si aucune séquence n'existe.
     */
    public function createDefaultSequence(Tenant $tenant, bool $forCreditNote = false): InvoiceSequence
    {
        $seq = new InvoiceSequence();
        $seq->setTenant($tenant);
        $seq->setName($forCreditNote ? 'Séquence avoirs' : 'Séquence principale');
        $seq->setPrefix($forCreditNote ? 'AV' : 'FAC');
        $seq->setYearFormat('AAAA');
        $seq->setIncludeMonth(false);
        $seq->setSeparator('-');
        $seq->setPadding(4);
        $seq->setStartNumber(1);
        $seq->setNextNumber(1);
        $seq->setResetYearly(false);
        $seq->setIsCreditNoteSequence($forCreditNote);

        $this->em->persist($seq);

        return $seq;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Privé
    // ────────────────────────────────────────────────────────────────────────

    private function format(InvoiceSequence $seq, int $number, \DateTimeImmutable $date): string
    {
        $sep    = $seq->getSeparator() ?? '';
        $parts  = [];

        // 1. Préfixe (ex: FAC, AV)
        if ($seq->getPrefix()) {
            $parts[] = $seq->getPrefix();
        }

        // 2. Année
        if ($seq->getYearFormat()) {
            $parts[] = match ($seq->getYearFormat()) {
                'AAAA' => $date->format('Y'),      // 2026
                'AA'   => $date->format('y'),       // 26
                default => '',
            };
        }

        // 3. Mois (optionnel)
        if ($seq->isIncludeMonth()) {
            $parts[] = $date->format('m');
        }

        // 4. Numéro padded (ex: 0001)
        $parts[] = str_pad((string) $number, $seq->getPadding(), '0', STR_PAD_LEFT);

        // Filtrer les segments vides et assembler
        return implode($sep, array_filter($parts, static fn(string $p) => $p !== ''));
    }
}
