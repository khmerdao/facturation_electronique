<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;

/**
 * Calcule les montants HT, TVA et TTC d'une facture et de ses lignes.
 *
 * Toutes les opérations utilisent bcmath (précision arbitraire) pour éviter
 * les erreurs d'arrondi des floats. Les montants sont stockés en DECIMAL(14,2)
 * mais calculés avec 4 décimales intermédiaires avant arrondi final.
 *
 * Formules (conformes à la réglementation française) :
 *   amountHt  = ROUND(quantity × unitPrice × (1 - discount/100), 2)
 *   amountTva = ROUND(amountHt × tvaRate / 100, 2)
 *   totalHt   = SUM(amountHt des lignes non-commentaire)
 *   totalTva  = SUM(amountTva des lignes)
 *   totalTtc  = totalHt + totalTva
 */
final class InvoiceCalculatorService
{
    /** Précision intermédiaire des calculs bcmath. */
    private const SCALE = 4;

    /**
     * Calcule et met à jour les montants d'une ligne.
     * Ne flush pas — appelé dans le même flush que la facture.
     */
    public function calculateLine(InvoiceLine $line): void
    {
        if ($line->isComment()) {
            $line->setAmountHt('0.00');
            $line->setAmountTva('0.00');

            return;
        }

        $qty       = $line->getQuantity();        // string DECIMAL(14,4)
        $price     = $line->getUnitPrice();       // string DECIMAL(14,4)
        $discount  = $line->getDiscount();        // string DECIMAL(5,2)
        $tvaRate   = $line->getTvaRate();         // string DECIMAL(5,2)

        // amountHt = qty × price × (1 - discount/100)
        $discountFactor = bcsub('1', bcdiv($discount, '100', self::SCALE), self::SCALE);
        $amountHt       = bcmul(bcmul($qty, $price, self::SCALE), $discountFactor, self::SCALE);
        $amountHt       = $this->round2($amountHt);

        // amountTva = amountHt × tvaRate / 100
        $amountTva = bcmul($amountHt, bcdiv($tvaRate, '100', self::SCALE), self::SCALE);
        $amountTva = $this->round2($amountTva);

        $line->setAmountHt($amountHt);
        $line->setAmountTva($amountTva);
    }

    /**
     * Recalcule toutes les lignes puis agrège les totaux sur la facture.
     */
    public function recalculate(Invoice $invoice): void
    {
        $totalHt  = '0.00';
        $totalTva = '0.00';

        foreach ($invoice->getLines() as $line) {
            $this->calculateLine($line);
            $totalHt  = bcadd($totalHt, $line->getAmountHt(), 2);
            $totalTva = bcadd($totalTva, $line->getAmountTva(), 2);
        }

        $totalTtc = bcadd($totalHt, $totalTva, 2);

        $invoice->setTotalHt($totalHt);
        $invoice->setTotalTva($totalTva);
        $invoice->setTotalTtc($totalTtc);
    }

    /**
     * Retourne un tableau des montants TVA groupés par taux.
     * Utilisé pour la décomposition TVA sur la facture et le FEC.
     *
     * @return array<string, array{base: string, tva: string}> clé = taux (ex: "20.00")
     */
    public function getTvaBreakdown(Invoice $invoice): array
    {
        $breakdown = [];

        foreach ($invoice->getLines() as $line) {
            if ($line->isComment()) {
                continue;
            }

            $rate = $line->getTvaRate();

            if (!isset($breakdown[$rate])) {
                $breakdown[$rate] = ['base' => '0.00', 'tva' => '0.00'];
            }

            $breakdown[$rate]['base'] = bcadd($breakdown[$rate]['base'], $line->getAmountHt(), 2);
            $breakdown[$rate]['tva']  = bcadd($breakdown[$rate]['tva'], $line->getAmountTva(), 2);
        }

        ksort($breakdown);

        return $breakdown;
    }

    /**
     * Calcule le montant restant dû (totalTtc - amountPaid).
     */
    public function getRemainingDue(Invoice $invoice): string
    {
        return bcsub($invoice->getTotalTtc(), $invoice->getAmountPaid(), 2);
    }

    /**
     * Vérifie si la facture est entièrement payée (à 1 centime près).
     */
    public function isFullyPaid(Invoice $invoice): bool
    {
        return bccomp($this->getRemainingDue($invoice), '0.01', 2) < 0;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Arrondi bancaire (half-up) à 2 décimales, retourne une string.
     */
    private function round2(string $value): string
    {
        // bcmath n'a pas de round natif — on ajoute 0.005 et on tronque
        $sign   = bccomp($value, '0', self::SCALE) < 0 ? '-' : '';
        $abs    = ltrim($value, '-');
        $shifted = bcadd($abs, '0.005', self::SCALE);
        $parts  = explode('.', $shifted);
        $dec    = isset($parts[1]) ? substr($parts[1], 0, 2) : '00';

        return $sign . $parts[0] . '.' . str_pad($dec, 2, '0');
    }
}
