<?php
declare(strict_types=1);
namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->formatMoney(...)),
            new TwigFilter('money_sign', $this->formatMoneyWithSign(...)),
        ];
    }

    /**
     * Formate un montant : "1 234,56 €"
     * {{ invoice.totalTtc|money }}
     * {{ invoice.totalTtc|money('USD') }}
     */
    public function formatMoney(string|float|null $amount, string $currency = 'EUR'): string
    {
        if ($amount === null) return '—';

        $value = is_string($amount) ? (float) $amount : $amount;

        $fmt = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);
        return $fmt->formatCurrency($value, $currency);
    }

    /**
     * Ajoute + devant les positifs : "+1 234,56 €" / "−456,00 €"
     */
    public function formatMoneyWithSign(string|float|null $amount, string $currency = 'EUR'): string
    {
        if ($amount === null) return '—';
        $value = is_string($amount) ? (float) $amount : $amount;
        $formatted = $this->formatMoney(abs($value), $currency);
        return $value >= 0 ? '+' . $formatted : '−' . $formatted;
    }
}
