<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Régime de TVA — détermine la périodicité des déclarations. */
enum VatRegime: string
{
    case REEL_NORMAL = 'REEL_NORMAL';         // CA3 mensuelle
    case REEL_SIMPLIFIE = 'REEL_SIMPLIFIE';   // CA12 annuelle
    case FRANCHISE_BASE = 'FRANCHISE_BASE';   // art. 293 B CGI
    case MICRO = 'MICRO';                     // micro-BIC / micro-BNC

    public function isVatApplicable(): bool
    {
        return $this !== self::FRANCHISE_BASE && $this !== self::MICRO;
    }
}
