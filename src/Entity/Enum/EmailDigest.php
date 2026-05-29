<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EmailDigest: string
{
    case IMMEDIATE = 'IMMEDIATE';
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
}
