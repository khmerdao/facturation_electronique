<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ExportType: string
{
    case FEC = 'FEC';
    case CSV = 'CSV';
    case XML = 'XML';
}
