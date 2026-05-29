<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum TenantStatus: string
{
    case ONBOARDING = 'ONBOARDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case DELETED = 'DELETED';
}
