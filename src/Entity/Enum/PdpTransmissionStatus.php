<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PdpTransmissionStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case REJECTED = 'REJECTED';
    case ERROR = 'ERROR';
}
