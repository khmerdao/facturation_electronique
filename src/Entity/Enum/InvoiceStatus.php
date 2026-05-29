<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Cycle de vie d'une facture émise (conforme au cycle DGFiP).
 * DRAFT → VALIDATED → SENT → ACKNOWLEDGED → PAID
 *                       ↘ REJECTED
 *         ↘ CANCELLED
 */
enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case VALIDATED = 'VALIDATED';
    case SENT = 'SENT';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case REJECTED = 'REJECTED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::VALIDATED => 'Validée',
            self::SENT => 'Transmise PDP',
            self::ACKNOWLEDGED => 'Reçue & acceptée',
            self::REJECTED => 'Rejetée',
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
        };
    }

    /** La facture est-elle encore modifiable ? (uniquement en DRAFT) */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /** Un avoir peut-il être émis ? (uniquement après acceptation) */
    public function canIssueCreditNote(): bool
    {
        return $this === self::ACKNOWLEDGED || $this === self::PAID;
    }

    /** Un paiement peut-il être enregistré ? */
    public function canRecordPayment(): bool
    {
        return $this === self::ACKNOWLEDGED;
    }

    /** Transitions autorisées depuis ce statut. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::VALIDATED, self::CANCELLED],
            self::VALIDATED => [self::SENT, self::CANCELLED],
            self::SENT => [self::ACKNOWLEDGED, self::REJECTED],
            self::REJECTED => [self::VALIDATED],
            self::ACKNOWLEDGED => [self::PAID, self::CANCELLED],
            self::PAID, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
