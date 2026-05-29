<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ApiEnvironment: string
{
    case PRODUCTION = 'PRODUCTION';
    case TEST = 'TEST';
}
