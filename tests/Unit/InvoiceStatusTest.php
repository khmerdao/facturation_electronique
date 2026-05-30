<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Enum\InvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'enum InvoiceStatus.
 *
 * Valide les règles de transition du cycle de vie DGFiP :
 *   DRAFT → VALIDATED → SENT → ACKNOWLEDGED → PAID
 *                      ↓               ↓
 *                   REJECTED       CANCELLED
 */
final class InvoiceStatusTest extends TestCase
{
    // ── Transitions autorisées ────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideAllowedTransitions')]
    public function allowed_transition(InvoiceStatus $from, InvoiceStatus $to): void
    {
        self::assertTrue(
            $from->canTransitionTo($to),
            sprintf('La transition %s → %s devrait être autorisée', $from->value, $to->value),
        );
    }

    public static function provideAllowedTransitions(): array
    {
        return [
            'draft_to_validated'           => [InvoiceStatus::DRAFT,        InvoiceStatus::VALIDATED],
            'draft_to_cancelled'           => [InvoiceStatus::DRAFT,        InvoiceStatus::CANCELLED],
            'validated_to_sent'            => [InvoiceStatus::VALIDATED,    InvoiceStatus::SENT],
            'validated_to_cancelled'       => [InvoiceStatus::VALIDATED,    InvoiceStatus::CANCELLED],
            'sent_to_acknowledged'         => [InvoiceStatus::SENT,         InvoiceStatus::ACKNOWLEDGED],
            'sent_to_rejected'             => [InvoiceStatus::SENT,         InvoiceStatus::REJECTED],
            'rejected_to_validated'        => [InvoiceStatus::REJECTED,     InvoiceStatus::VALIDATED],
            'acknowledged_to_paid'         => [InvoiceStatus::ACKNOWLEDGED, InvoiceStatus::PAID],
            'acknowledged_to_cancelled'    => [InvoiceStatus::ACKNOWLEDGED, InvoiceStatus::CANCELLED],
        ];
    }

    // ── Transitions interdites ────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideForbiddenTransitions')]
    public function forbidden_transition(InvoiceStatus $from, InvoiceStatus $to): void
    {
        self::assertFalse(
            $from->canTransitionTo($to),
            sprintf('La transition %s → %s devrait être INTERDITE', $from->value, $to->value),
        );
    }

    public static function provideForbiddenTransitions(): array
    {
        return [
            'draft_to_sent'             => [InvoiceStatus::DRAFT,        InvoiceStatus::SENT],
            'draft_to_acknowledged'     => [InvoiceStatus::DRAFT,        InvoiceStatus::ACKNOWLEDGED],
            'draft_to_paid'             => [InvoiceStatus::DRAFT,        InvoiceStatus::PAID],
            'validated_to_acknowledged' => [InvoiceStatus::VALIDATED,    InvoiceStatus::ACKNOWLEDGED],
            'validated_to_paid'         => [InvoiceStatus::VALIDATED,    InvoiceStatus::PAID],
            'sent_to_paid'              => [InvoiceStatus::SENT,         InvoiceStatus::PAID],
            'paid_to_cancelled'         => [InvoiceStatus::PAID,         InvoiceStatus::CANCELLED],
            'cancelled_to_draft'        => [InvoiceStatus::CANCELLED,    InvoiceStatus::DRAFT],
            'cancelled_to_validated'    => [InvoiceStatus::CANCELLED,    InvoiceStatus::VALIDATED],
        ];
    }

    // ── canIssueCreditNote() ──────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideStatusesForCreditNote')]
    public function can_issue_credit_note(InvoiceStatus $status, bool $expected): void
    {
        self::assertSame(
            $expected,
            $status->canIssueCreditNote(),
            sprintf('canIssueCreditNote() sur %s devrait retourner %s', $status->value, $expected ? 'true' : 'false'),
        );
    }

    public static function provideStatusesForCreditNote(): array
    {
        return [
            'acknowledged_yes' => [InvoiceStatus::ACKNOWLEDGED, true],
            'paid_yes'         => [InvoiceStatus::PAID,         true],
            'draft_no'         => [InvoiceStatus::DRAFT,        false],
            'validated_no'     => [InvoiceStatus::VALIDATED,    false],
            'sent_no'          => [InvoiceStatus::SENT,         false],
            'rejected_no'      => [InvoiceStatus::REJECTED,     false],
            'cancelled_no'     => [InvoiceStatus::CANCELLED,    false],
        ];
    }

    // ── canRecordPayment() ────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideStatusesForPayment')]
    public function can_record_payment(InvoiceStatus $status, bool $expected): void
    {
        self::assertSame(
            $expected,
            $status->canRecordPayment(),
            sprintf('canRecordPayment() sur %s devrait retourner %s', $status->value, $expected ? 'true' : 'false'),
        );
    }

    public static function provideStatusesForPayment(): array
    {
        return [
            'acknowledged_yes' => [InvoiceStatus::ACKNOWLEDGED, true],
            'draft_no'         => [InvoiceStatus::DRAFT,        false],
            'validated_no'     => [InvoiceStatus::VALIDATED,    false],
            'sent_no'          => [InvoiceStatus::SENT,         false],
            'paid_no'          => [InvoiceStatus::PAID,         false],
            'cancelled_no'     => [InvoiceStatus::CANCELLED,    false],
        ];
    }

    // ── label() ───────────────────────────────────────────────────────────────

    #[Test]
    public function all_statuses_have_non_empty_label(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            self::assertNotEmpty(
                $status->label(),
                sprintf('Le statut %s doit avoir un label non vide', $status->value),
            );
        }
    }
}
