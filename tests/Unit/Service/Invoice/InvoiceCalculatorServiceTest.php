<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Invoice\InvoiceCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de InvoiceCalculatorService.
 *
 * Couvre :
 *  - Calcul HT par ligne (qté × prix × remise)
 *  - Calcul TVA par ligne
 *  - Agrégation des totaux
 *  - Décomposition TVA par taux
 *  - Arrondi bcmath (half-up, 2 décimales)
 *  - Lignes commentaires (ignorées dans les totaux)
 *  - Facture entièrement payée / partiellement payée
 */
final class InvoiceCalculatorServiceTest extends TestCase
{
    private InvoiceCalculatorService $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InvoiceCalculatorService();
    }

    // ── calculateLine ─────────────────────────────────────────────────────────

    #[Test]
    public function calculateLine_simple(): void
    {
        $line = $this->makeLine('2', '100.00', '0', '20.00');

        $this->calculator->calculateLine($line);

        self::assertSame('200.00', $line->getAmountHt());
        self::assertSame('40.00', $line->getAmountTva());
    }

    #[Test]
    public function calculateLine_with_discount(): void
    {
        // 5 × 100 HT avec 10% de remise = 450 HT
        $line = $this->makeLine('5', '100.00', '10.00', '20.00');

        $this->calculator->calculateLine($line);

        self::assertSame('450.00', $line->getAmountHt());
        self::assertSame('90.00', $line->getAmountTva());
    }

    #[Test]
    public function calculateLine_decimal_quantity(): void
    {
        // 1.5h × 90€ = 135€ HT, TVA 20% = 27€
        $line = $this->makeLine('1.5', '90.00', '0', '20.00');

        $this->calculator->calculateLine($line);

        self::assertSame('135.00', $line->getAmountHt());
        self::assertSame('27.00', $line->getAmountTva());
    }

    #[Test]
    public function calculateLine_zero_tva(): void
    {
        $line = $this->makeLine('1', '500.00', '0', '0.00');

        $this->calculator->calculateLine($line);

        self::assertSame('500.00', $line->getAmountHt());
        self::assertSame('0.00', $line->getAmountTva());
    }

    #[Test]
    public function calculateLine_reduced_tva_5_5(): void
    {
        // Livres : TVA 5.5%
        $line = $this->makeLine('3', '20.00', '0', '5.50');

        $this->calculator->calculateLine($line);

        self::assertSame('60.00', $line->getAmountHt());
        self::assertSame('3.30', $line->getAmountTva());
    }

    #[Test]
    public function calculateLine_comment_returns_zero(): void
    {
        $line = $this->makeLine('1', '999.00', '0', '20.00');
        $line->setIsComment(true);

        $this->calculator->calculateLine($line);

        self::assertSame('0.00', $line->getAmountHt());
        self::assertSame('0.00', $line->getAmountTva());
    }

    #[Test]
    #[DataProvider('provideRoundingCases')]
    public function calculateLine_rounding(
        string $qty, string $price, string $discount, string $tva,
        string $expectedHt, string $expectedTva,
    ): void {
        $line = $this->makeLine($qty, $price, $discount, $tva);
        $this->calculator->calculateLine($line);

        self::assertSame($expectedHt,  $line->getAmountHt(),  "HT attendu $expectedHt");
        self::assertSame($expectedTva, $line->getAmountTva(), "TVA attendue $expectedTva");
    }

    public static function provideRoundingCases(): array
    {
        return [
            // Arrondi classique
            'arrondi_bas'  => ['1', '10.333', '0', '20.00', '10.33', '2.07'],
            'arrondi_haut' => ['1', '10.665', '0', '20.00', '10.67', '2.13'],
            // Cas limite centimes
            'centimes'     => ['3', '0.333', '0', '20.00', '1.00', '0.20'],
            // Remise fractionnelle
            'remise_frac'  => ['2', '100.00', '33.33', '20.00', '133.34', '26.67'],
        ];
    }

    // ── recalculate ───────────────────────────────────────────────────────────

    #[Test]
    public function recalculate_aggregates_totals(): void
    {
        $invoice = new Invoice();

        $invoice->addLine($this->makeLine('2', '100.00', '0', '20.00')); // HT 200, TVA 40
        $invoice->addLine($this->makeLine('1', '50.00',  '0', '10.00')); // HT 50,  TVA 5

        foreach ($invoice->getLines() as $line) {
            $line->setInvoice($invoice);
        }

        $this->calculator->recalculate($invoice);

        self::assertSame('250.00', $invoice->getTotalHt());
        self::assertSame('45.00',  $invoice->getTotalTva());
        self::assertSame('295.00', $invoice->getTotalTtc());
    }

    #[Test]
    public function recalculate_ignores_comment_lines(): void
    {
        $invoice = new Invoice();

        $realLine    = $this->makeLine('1', '100.00', '0', '20.00');
        $commentLine = $this->makeLine('99', '999.00', '0', '20.00');
        $commentLine->setIsComment(true);
        $realLine->setInvoice($invoice);
        $commentLine->setInvoice($invoice);

        $invoice->addLine($realLine);
        $invoice->addLine($commentLine);

        $this->calculator->recalculate($invoice);

        self::assertSame('100.00', $invoice->getTotalHt());
        self::assertSame('20.00',  $invoice->getTotalTva());
        self::assertSame('120.00', $invoice->getTotalTtc());
    }

    #[Test]
    public function recalculate_empty_invoice(): void
    {
        $invoice = new Invoice();

        $this->calculator->recalculate($invoice);

        self::assertSame('0.00', $invoice->getTotalHt());
        self::assertSame('0.00', $invoice->getTotalTva());
        self::assertSame('0.00', $invoice->getTotalTtc());
    }

    // ── getTvaBreakdown ───────────────────────────────────────────────────────

    #[Test]
    public function getTvaBreakdown_groups_by_rate(): void
    {
        $invoice = new Invoice();

        $line1 = $this->makeLine('1', '100.00', '0', '20.00');
        $line2 = $this->makeLine('2', '50.00',  '0', '20.00');  // même taux → regroupé
        $line3 = $this->makeLine('1', '200.00', '0', '10.00');  // taux différent
        foreach ([$line1, $line2, $line3] as $l) {
            $l->setInvoice($invoice);
            $this->calculator->calculateLine($l);
            $invoice->addLine($l);
        }

        $breakdown = $this->calculator->getTvaBreakdown($invoice);

        self::assertArrayHasKey('20.00', $breakdown);
        self::assertArrayHasKey('10.00', $breakdown);
        self::assertSame('200.00', $breakdown['20.00']['base']); // 100 + 100
        self::assertSame('40.00',  $breakdown['20.00']['tva']);  // 20 + 20
        self::assertSame('200.00', $breakdown['10.00']['base']);
        self::assertSame('20.00',  $breakdown['10.00']['tva']);
    }

    #[Test]
    public function getTvaBreakdown_excludes_comment_lines(): void
    {
        $invoice = new Invoice();

        $real    = $this->makeLine('1', '100.00', '0', '20.00');
        $comment = $this->makeLine('1', '500.00', '0', '20.00');
        $comment->setIsComment(true);
        $real->setInvoice($invoice);
        $comment->setInvoice($invoice);
        $this->calculator->calculateLine($real);
        $invoice->addLine($real);
        $invoice->addLine($comment);

        $breakdown = $this->calculator->getTvaBreakdown($invoice);

        self::assertSame('100.00', $breakdown['20.00']['base']);
        self::assertSame('20.00',  $breakdown['20.00']['tva']);
    }

    // ── getRemainingDue ───────────────────────────────────────────────────────

    #[Test]
    public function getRemainingDue_unpaid(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalTtc('300.00');
        $invoice->setAmountPaid('0.00');

        self::assertSame('300.00', $this->calculator->getRemainingDue($invoice));
    }

    #[Test]
    public function getRemainingDue_partial_payment(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalTtc('300.00');
        $invoice->setAmountPaid('100.00');

        self::assertSame('200.00', $this->calculator->getRemainingDue($invoice));
    }

    #[Test]
    public function getRemainingDue_fully_paid(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalTtc('300.00');
        $invoice->setAmountPaid('300.00');

        self::assertSame('0.00', $this->calculator->getRemainingDue($invoice));
    }

    // ── isFullyPaid ───────────────────────────────────────────────────────────

    #[Test]
    public function isFullyPaid_returns_false_when_unpaid(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalTtc('100.00');
        $invoice->setAmountPaid('0.00');

        self::assertFalse($this->calculator->isFullyPaid($invoice));
    }

    #[Test]
    public function isFullyPaid_returns_true_when_paid(): void
    {
        $invoice = new Invoice();
        $invoice->setTotalTtc('100.00');
        $invoice->setAmountPaid('100.00');

        self::assertTrue($this->calculator->isFullyPaid($invoice));
    }

    #[Test]
    public function isFullyPaid_tolerates_one_cent_delta(): void
    {
        // Tolérance d'1 centime pour les arrondis
        $invoice = new Invoice();
        $invoice->setTotalTtc('100.00');
        $invoice->setAmountPaid('99.99');

        self::assertTrue($this->calculator->isFullyPaid($invoice));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeLine(
        string $qty,
        string $price,
        string $discount,
        string $tvaRate,
    ): InvoiceLine {
        $line = new InvoiceLine();
        $line->setQuantity($qty);
        $line->setUnitPrice($price);
        $line->setDiscount($discount);
        $line->setTvaRate($tvaRate);
        $line->setIsComment(false);
        $line->setDescription('Ligne de test');
        $line->setUnit('U');
        $line->setPosition(0);

        return $line;
    }
}
