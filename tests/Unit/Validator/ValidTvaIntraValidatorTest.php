<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\Constraint\ValidTvaIntra;
use App\Validator\Constraint\ValidTvaIntraValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\ConstraintValidatorTestCase;

/**
 * Tests unitaires du validator TVA intracommunautaire.
 *
 * Patterns par pays implémentés : FR, DE, ES, IT, BE, NL
 * Fallback générique : /^[A-Z]{2}[A-Z0-9]{2,12}$/
 */
final class ValidTvaIntraValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidTvaIntraValidator
    {
        return new ValidTvaIntraValidator();
    }

    // ── Null / vide ───────────────────────────────────────────────────────

    #[Test]
    public function null_passes(): void
    {
        $this->validator->validate(null, new ValidTvaIntra());
        $this->assertNoViolation();
    }

    #[Test]
    public function empty_string_passes(): void
    {
        $this->validator->validate('', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    // ── TVA France ────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideValidFrTva')]
    public function valid_french_tva_passes(string $tva): void
    {
        $this->validator->validate($tva, new ValidTvaIntra());
        $this->assertNoViolation();
    }

    public static function provideValidFrTva(): array
    {
        return [
            'standard numérique'     => ['FR12345678901'],
            'clé alphanumérique 1'   => ['FRA2345678901'],
            'clé alphanumérique 2'   => ['FR1B345678901'],
            'double alpha'           => ['FRAB345678901'],
            'avec espaces nettoyés'  => ['FR 12 345678901'],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidFrTva')]
    public function invalid_french_tva_raises_violation(string $tva): void
    {
        $this->validator->validate($tva, new ValidTvaIntra());

        $this->buildViolation((new ValidTvaIntra())->message)
            ->setParameter('{{ value }}', strtoupper(preg_replace('/\s/', '', $tva)))
            ->assertRaised();
    }

    public static function provideInvalidFrTva(): array
    {
        return [
            'trop court (12 chiffres)'  => ['FR1234567890'],
            'trop long (14 chiffres)'   => ['FR12345678901234'],
            'sans préfixe FR'           => ['12345678901'],
        ];
    }

    // ── TVA Allemagne ─────────────────────────────────────────────────────

    #[Test]
    public function valid_german_tva_passes(): void
    {
        $this->validator->validate('DE123456789', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    #[Test]
    public function invalid_german_tva_raises_violation(): void
    {
        $this->validator->validate('DE12345678', new ValidTvaIntra()); // 8 chiffres au lieu de 9
        $this->buildViolation((new ValidTvaIntra())->message)
            ->setParameter('{{ value }}', 'DE12345678')
            ->assertRaised();
    }

    // ── TVA Espagne ───────────────────────────────────────────────────────

    #[Test]
    public function valid_spanish_tva_passes(): void
    {
        $this->validator->validate('ESA12345678', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    // ── TVA Belgique ──────────────────────────────────────────────────────

    #[Test]
    public function valid_belgian_tva_passes(): void
    {
        $this->validator->validate('BE0123456789', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    #[Test]
    public function invalid_belgian_tva_missing_zero_raises_violation(): void
    {
        // Belgique : doit commencer par BE0
        $this->validator->validate('BE1123456789', new ValidTvaIntra());
        $this->buildViolation((new ValidTvaIntra())->message)
            ->setParameter('{{ value }}', 'BE1123456789')
            ->assertRaised();
    }

    // ── TVA Pays-Bas ──────────────────────────────────────────────────────

    #[Test]
    public function valid_dutch_tva_passes(): void
    {
        $this->validator->validate('NL123456789B01', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    // ── Insensibilité à la casse ──────────────────────────────────────────

    #[Test]
    public function lowercase_is_normalized_before_validation(): void
    {
        $this->validator->validate('fr12345678901', new ValidTvaIntra());
        $this->assertNoViolation();
    }

    // ── Trop court ────────────────────────────────────────────────────────

    #[Test]
    public function too_short_string_raises_violation(): void
    {
        $this->validator->validate('FR1', new ValidTvaIntra());
        $this->buildViolation((new ValidTvaIntra())->message)
            ->setParameter('{{ value }}', 'FR1')
            ->assertRaised();
    }

    // ── Pays inconnu (fallback générique) ────────────────────────────────

    #[Test]
    public function unknown_country_uses_generic_pattern(): void
    {
        // XX n'est pas dans les patterns spécifiques → fallback /^[A-Z]{2}[A-Z0-9]{2,12}$/
        $this->validator->validate('XXAB123456', new ValidTvaIntra());
        $this->assertNoViolation();
    }
}
