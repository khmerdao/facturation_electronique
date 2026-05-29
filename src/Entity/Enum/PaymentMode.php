<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PaymentMode: string
{
    case VIREMENT = 'VIREMENT';
    case CHEQUE = 'CHEQUE';
    case CARTE = 'CARTE';
    case PRELEVEMENT = 'PRELEVEMENT';
    case ESPECES = 'ESPECES';
    case COMPENSATION = 'COMPENSATION';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::VIREMENT => 'Virement bancaire',
            self::CHEQUE => 'Chèque',
            self::CARTE => 'Carte bancaire',
            self::PRELEVEMENT => 'Prélèvement automatique',
            self::ESPECES => 'Espèces',
            self::COMPENSATION => 'Compensation / avoir',
            self::AUTRE => 'Autre',
        };
    }

    /** Code DGFiP normalisé pour l'e-reporting paiement. */
    public function dgfipCode(): string
    {
        return match ($this) {
            self::VIREMENT => '30',
            self::PRELEVEMENT => '31',
            self::CARTE => '40',
            self::CHEQUE => '50',
            self::ESPECES => '60',
            self::COMPENSATION => '70',
            self::AUTRE => '99',
        };
    }
}
