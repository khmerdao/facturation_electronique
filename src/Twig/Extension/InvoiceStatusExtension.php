<?php
declare(strict_types=1);
namespace App\Twig\Extension;

use App\Entity\Enum\InvoiceStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class InvoiceStatusExtension extends AbstractExtension
{
    private const BADGE_COLORS = [
        'DRAFT'        => 'secondary',
        'VALIDATED'    => 'info',
        'SENT'         => 'primary',
        'ACKNOWLEDGED' => 'success',
        'REJECTED'     => 'danger',
        'PAID'         => 'success',
        'CANCELLED'    => 'dark',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('invoice_status_label', $this->label(...)),
            new TwigFilter('invoice_status_color', $this->color(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('invoice_status_badge', $this->badge(...), ['is_safe' => ['html']]),
        ];
    }

    public function label(string|InvoiceStatus $status): string
    {
        $s = is_string($status) ? InvoiceStatus::tryFrom($status) : $status;
        return $s?->label() ?? $status;
    }

    public function color(string|InvoiceStatus $status): string
    {
        $value = $status instanceof InvoiceStatus ? $status->value : $status;
        return self::BADGE_COLORS[$value] ?? 'secondary';
    }

    public function badge(string|InvoiceStatus $status): string
    {
        $label = $this->label($status);
        $color = $this->color($status);
        return sprintf('<span class="badge bg-%s">%s</span>', $color, htmlspecialchars($label));
    }
}
