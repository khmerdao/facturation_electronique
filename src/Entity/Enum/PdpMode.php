<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PdpMode: string
{
    case PPF = 'PPF';   // Portail Public de Facturation (Chorus Pro)
    case PDP = 'PDP';   // Plateforme de Dématérialisation Partenaire
}
