<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EReportingTransactionType: string
{
    case B2C = 'B2C';
    case INTRACOM = 'INTRACOM';
    case EXPORT = 'EXPORT';
    case FOREIGN_SERVICE = 'FOREIGN_SERVICE';
}
