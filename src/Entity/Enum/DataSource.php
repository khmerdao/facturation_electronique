<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Origine d'une donnée e-reporting : extraite automatiquement ou saisie. */
enum DataSource: string
{
    case AUTO = 'AUTO';
    case MANUAL = 'MANUAL';
}
