<?php

declare(strict_types=1);

namespace App\Service\Billing;

/**
 * Résultat d'une vérification de limite de plan.
 * Value object immuable.
 */
final class LimitCheckResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly int $current,
        public readonly ?int $limit,
    ) {}

    public static function allowed(int $current, ?int $limit): self
    {
        return new self(true, null, $current, $limit);
    }

    public static function denied(string $reason, int $current, int $limit): self
    {
        return new self(false, $reason, $current, $limit);
    }

    /** Pourcentage d'utilisation (0-100), 0 si illimité. */
    public function usagePercent(): int
    {
        if ($this->limit === null || $this->limit === 0) {
            return 0;
        }

        return (int) min(100, round($this->current / $this->limit * 100));
    }

    /** True si la limite est à 80% ou plus. */
    public function isNearLimit(): bool
    {
        return $this->limit !== null && $this->usagePercent() >= 80;
    }
}
