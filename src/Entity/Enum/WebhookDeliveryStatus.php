<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum WebhookDeliveryStatus: string
{
    case PENDING = 'PENDING';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
}
