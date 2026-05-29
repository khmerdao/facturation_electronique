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
    /** Code type document CII (UN/EDIFACT 1001). */
    public function ciiTypeCode(): string
    {
        return match ($this) {
            self::INVOICE     => '380',  // Facture commerciale
            self::CREDIT_NOTE => '381',  // Avoir
            self::PROFORMA    => '325',  // Proforma
        };
    }

    /** Code type document UBL 2.1. */
    public function ublTypeCode(): string
    {
        return $this->ciiTypeCode();
    }

}
