<?php
declare(strict_types=1);
namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SiretExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('siret_format', $this->formatSiret(...)),
            new TwigFilter('siren_format', $this->formatSiren(...)),
        ];
    }

    /** "12345678901234" → "123 456 789 01234" */
    public function formatSiret(?string $siret): string
    {
        if (null === $siret) return '—';
        $s = preg_replace('/[^0-9]/', '', $siret);
        if (strlen($s) !== 14) return $siret;
        return substr($s, 0, 3) . ' ' . substr($s, 3, 3) . ' ' . substr($s, 6, 3) . ' ' . substr($s, 9);
    }

    /** "123456789" → "123 456 789" */
    public function formatSiren(?string $siren): string
    {
        if (null === $siren) return '—';
        $s = preg_replace('/[^0-9]/', '', $siren);
        if (strlen($s) !== 9) return $siren;
        return substr($s, 0, 3) . ' ' . substr($s, 3, 3) . ' ' . substr($s, 6);
    }
}
