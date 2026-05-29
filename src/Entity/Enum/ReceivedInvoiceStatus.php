<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ReceivedInvoiceStatus: string
{
    case PENDING_VALIDATION = 'PENDING_VALIDATION';
    case APPROVED = 'APPROVED';
    case CONTESTED = 'CONTESTED';
    case REJECTED = 'REJECTED';
    case PAID = 'PAID';
    case PARSE_ERROR = 'PARSE_ERROR';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_VALIDATION => 'À valider',
            self::APPROVED => 'Validée',
            self::CONTESTED => 'Contestée',
            self::REJECTED => 'Rejetée',
            self::PAID => 'Payée',
            self::PARSE_ERROR => 'Erreur de lecture',
        };
    }

    public function canRecordPayment(): bool
    {
        return $this === self::APPROVED;
    }
}
