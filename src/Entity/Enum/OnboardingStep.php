<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum OnboardingStep: string
{
    case ORGANISATION = 'ORGANISATION';
    case PREFERENCES = 'PREFERENCES';
    case COMPLETED = 'COMPLETED';
}
