<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\Constraint\ValidSiret;
use App\Validator\Constraint\ValidSiretValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\ConstraintValidatorTestCase;

/**
 * Tests unitaires du validator SIRET.
 *
 * SIRET valide = SIREN (9 chiffres) + NIC (5 chiffres), vérifié par l'algorithme de Luhn.
 * Le SIRET de l'État français (35600000000048) est un cas de référence classique.
 */
final class ValidSiretValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidSiretValidator
    {
        return new ValidSiretValidator();
    }

    // ── Cas valides ───────────────────────────────────────────────────────────

    #[Test]
    public function null_is_valid(): void
    {
        $this->validator->validate(null, new ValidSiret());
        $this->assertNoViolation();
    }

    #[Test]
    public function empty_string_is_valid(): void
    {
        $this->validator->validate('', new ValidSiret());
        $this->assertNoViolation();
    }

    #[Test]
    #[DataProvider('provideValidSirets')]
    public function valid_siret_passes(string $siret): void
    {
        $this->validator->validate($siret, new ValidSiret());
        $this->assertNoViolation();
    }

    public static function provideValidSirets(): array
    {
        return [
            'état français'     => ['35600000000048'],
            'avec espaces'      => ['356 000 000 00048'],  // espaces tolérés (nettoyés)
            'autre valide 1'    => ['73282932000074'],
            'autre valide 2'    => ['80295478400021'],
            'apple france'      => ['38716013200032'],
        ];
    }

    // ── Cas invalides ─────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideInvalidSirets')]
    public function invalid_siret_raises_violation(string $siret): void
    {
        $this->validator->validate($siret, new ValidSiret());
        $this->buildViolation((new ValidSiret())->message)
            ->setParameter('{{ value }}', $siret)
            ->assertRaised();
    }

    public static function provideInvalidSirets(): array
    {
        return [
            'trop court'         => ['1234567890'],
            'trop long'          => ['123456789012345'],
            'luhn invalide'      => ['35600000000047'], // dernier chiffre changé
            'tous zéros'         => ['00000000000000'],
            'lettres'            => ['1234ABCD901234'],
        ];
    }

    #[Test]
    public function violation_message_contains_invalid_value(): void
    {
        $invalidSiret = '12345678901234'; // Luhn invalide

        $this->validator->validate($invalidSiret, new ValidSiret());

        $violations = $this->context->getViolations();
        self::assertCount(1, $violations);
        self::assertStringContainsString($invalidSiret, (string) $violations->get(0)->getMessage());
    }
}
