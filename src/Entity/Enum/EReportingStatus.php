<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EReportingStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case DRAFT = 'DRAFT';
    case READY = 'READY';
    case SUBMITTED = 'SUBMITTED';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case LATE = 'LATE';
    case EMPTY = 'EMPTY';
}
