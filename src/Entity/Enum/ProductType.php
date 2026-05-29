<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ProductType: string
{
    case PRODUCT = 'PRODUCT';
    case SERVICE = 'SERVICE';
}
