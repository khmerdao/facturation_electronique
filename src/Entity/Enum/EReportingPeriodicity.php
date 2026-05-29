<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EReportingPeriodicity: string
{
    case MONTHLY = 'MONTHLY';
    case QUARTERLY = 'QUARTERLY';
}
