<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Motifs d'exonération de TVA (obligatoire si taux = 0%). */
enum VatExemptionReason: string
{
    case ART293B = 'EXEMPT_ART293B';        // Franchise en base
    case EXPORT = 'EXEMPT_EXPORT';          // Exportation hors UE
    case INTRACOM = 'EXEMPT_INTRACOM';      // Livraison intracommunautaire
    case AUTOLIQ = 'EXEMPT_AUTOLIQ';        // Autoliquidation (BTP)
    case DOM = 'EXEMPT_DOM';                // Régime DOM
    case OTHER = 'EXEMPT_OTHER';            // Autre (texte libre requis)

    public function label(): string
    {
        return match ($this) {
            self::ART293B => 'TVA non applicable, art. 293 B du CGI',
            self::EXPORT => 'Exonération TVA — exportation hors UE',
            self::INTRACOM => 'Exonération TVA — livraison intracommunautaire',
            self::AUTOLIQ => 'Autoliquidation de la TVA',
            self::DOM => 'Régime DOM',
            self::OTHER => 'Autre exonération',
        };
    }
    /** Code raison d'exonération CII (UNTDID 5305). */
    public function ciiCode(): string
    {
        return match ($this) {
            self::ART293B  => 'E',   // Exempt
            self::EXPORT   => 'G',   // Free export
            self::INTRACOM => 'K',   // Intra-community
            self::AUTOLIQ  => 'AE',  // VAT reverse charge
            self::DOM      => 'E',
            self::OTHER    => 'E',
        };
    }

}
