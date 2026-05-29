<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ContactType: string
{
    case CLIENT = 'CLIENT';
    case SUPPLIER = 'SUPPLIER';
    case BOTH = 'BOTH';

    public function isClient(): bool
    {
        return $this === self::CLIENT || $this === self::BOTH;
    }

    public function isSupplier(): bool
    {
        return $this === self::SUPPLIER || $this === self::BOTH;
    }
}
