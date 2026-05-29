<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Rôles intra-tenant. Le ROLE_SUPER_ADMIN est géré hors de cet enum
 * (firewall séparé, pas de TenantMembership).
 */
enum Role: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case ACCOUNTANT = 'ACCOUNTANT';
    case VIEWER = 'VIEWER';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur',
            self::ACCOUNTANT => 'Comptable',
            self::VIEWER => 'Lecture seule',
        };
    }

    /** Rôle Symfony Security correspondant. */
    public function asSecurityRole(): string
    {
        return 'ROLE_' . $this->value;
    }

    /** Niveau hiérarchique pour comparaison (plus haut = plus de droits). */
    public function level(): int
    {
        return match ($this) {
            self::OWNER => 40,
            self::ADMIN => 30,
            self::ACCOUNTANT => 20,
            self::VIEWER => 10,
        };
    }

    public function canManageUsers(): bool
    {
        return $this->level() >= self::ADMIN->level();
    }

    public function canEditInvoices(): bool
    {
        return $this->level() >= self::ACCOUNTANT->level();
    }
}
