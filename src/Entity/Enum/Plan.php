<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum Plan: string
{
    case FREE = 'FREE';
    case PRO = 'PRO';
    case ENTERPRISE = 'ENTERPRISE';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Gratuit',
            self::PRO => 'Pro',
            self::ENTERPRISE => 'Entreprise',
        };
    }

    /** Limite de factures par mois (null = illimité). */
    public function monthlyInvoiceLimit(): ?int
    {
        return match ($this) {
            self::FREE => 20,
            self::PRO => 500,
            self::ENTERPRISE => null,
        };
    }

    /** Limite d'utilisateurs actifs (null = illimité). */
    public function userLimit(): ?int
    {
        return match ($this) {
            self::FREE => 2,
            self::PRO => 10,
            self::ENTERPRISE => null,
        };
    }

    /** Quota de stockage S3 en octets (null = illimité). */
    public function storageLimitBytes(): ?int
    {
        return match ($this) {
            self::FREE => 1_073_741_824,       // 1 Go
            self::PRO => 10_737_418_240,        // 10 Go
            self::ENTERPRISE => null,
        };
    }
}
