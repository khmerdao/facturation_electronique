<?php
declare(strict_types=1);
namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateFrExtension extends AbstractExtension
{
    private static array $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', $this->dateFr(...)),
            new TwigFilter('date_short_fr', $this->dateShortFr(...)),
            new TwigFilter('date_relative', $this->dateRelative(...)),
        ];
    }

    /** "15 janvier 2026" */
    public function dateFr(\DateTimeInterface|string|null $date): string
    {
        if (null === $date) return '—';
        $dt = is_string($date) ? new \DateTimeImmutable($date) : $date;
        return sprintf('%d %s %d', $dt->format('j'), self::$months[(int)$dt->format('n')], $dt->format('Y'));
    }

    /** "15/01/2026" */
    public function dateShortFr(\DateTimeInterface|string|null $date): string
    {
        if (null === $date) return '—';
        $dt = is_string($date) ? new \DateTimeImmutable($date) : $date;
        return $dt->format('d/m/Y');
    }

    /** "il y a 3 jours" / "dans 5 jours" / "aujourd'hui" */
    public function dateRelative(\DateTimeInterface|string|null $date): string
    {
        if (null === $date) return '—';
        $dt   = is_string($date) ? new \DateTimeImmutable($date) : \DateTimeImmutable::createFromInterface($date);
        $now  = new \DateTimeImmutable();
        $diff = $now->diff($dt);
        $days = (int) $diff->days;

        if ($days === 0) return "aujourd'hui";
        if ($diff->invert) {
            return match(true) {
                $days === 1 => "hier",
                $days < 7  => "il y a $days jours",
                $days < 30 => "il y a " . (int)($days/7) . " semaine(s)",
                $days < 365 => "il y a " . (int)($days/30) . " mois",
                default    => "il y a " . (int)($days/365) . " an(s)",
            };
        }
        return match(true) {
            $days === 1 => "demain",
            $days < 7  => "dans $days jours",
            $days < 30 => "dans " . (int)($days/7) . " semaine(s)",
            default    => "dans " . (int)($days/30) . " mois",
        };
    }
}
