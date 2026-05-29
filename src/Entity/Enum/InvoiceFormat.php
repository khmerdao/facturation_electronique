<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Formats électroniques structurés conformes à la réforme. */
enum InvoiceFormat: string
{
    case FACTURX = 'FACTURX';   // PDF/A-3 + XML CII embarqué
    case UBL = 'UBL';           // UBL 2.1
    case CII = 'CII';           // CII D16B

    public function label(): string
    {
        return match ($this) {
            self::FACTURX => 'Factur-X',
            self::UBL => 'UBL 2.1',
            self::CII => 'CII D16B',
        };
    }
}
