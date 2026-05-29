<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum NotificationSeverity: string
{
    case INFO = 'INFO';
    case SUCCESS = 'SUCCESS';
    case WARNING = 'WARNING';
    case DANGER = 'DANGER';
}
