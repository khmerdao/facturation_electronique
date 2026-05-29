<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ExportStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case DONE = 'DONE';
    case ERROR = 'ERROR';
}
