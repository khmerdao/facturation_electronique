<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum InvoiceType: string
{
    case INVOICE = 'INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';
    case PROFORMA = 'PROFORMA';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Facture',
            self::CREDIT_NOTE => 'Avoir',
            self::PROFORMA => 'Proforma',
        };
    }
}
