<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\Constraint\ValidBic;
use App\Validator\Constraint\ValidBicValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\ConstraintValidatorTestCase;

/**
 * Tests unitaires du validator BIC/SWIFT.
 *
 * Format BIC : 4 lettres (banque) + 2 lettres (pays ISO 3166)
 *              + 2 alphanum (ville) + 3 optionnels (branche)
 * Ex : BNPAFRPP, DEUTDEDB, BNPAFRPPXXX
 */
final class ValidBicValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidBicValidator
    {
        return new ValidBicValidator();
    }

    // ── Null / vide : toujours valide ─────────────────────────────────────

    #[Test]
    public function null_passes(): void
    {
        $this->validator->validate(null, new ValidBic());
        $this->assertNoViolation();
    }

    #[Test]
    public function empty_string_passes(): void
    {
        $this->validator->validate('', new ValidBic());
        $this->assertNoViolation();
    }

    // ── BIC 8 caractères (sans branche) ──────────────────────────────────

    #[Test]
    #[DataProvider('provideValidBic8')]
    public function valid_bic_8_chars_passes(string $bic): void
    {
        $this->validator->validate($bic, new ValidBic());
        $this->assertNoViolation();
    }

    public static function provideValidBic8(): array
    {
        return [
            'BNP Paribas France'   => ['BNPAFRPP'],
            'Deutsche Bank'        => ['DEUTDEDB'],
            'Société Générale'     => ['SOGEFRPP'],
            'Crédit Agricole'      => ['AGRIFRPP'],
            'CIC France'           => ['CMCIFRPP'],
            'ING Luxembourg'       => ['CELLLULL'],
            'Minuscules normalisés' => ['bnpafrpp'], // doit être converti en majuscules
        ];
    }

    // ── BIC 11 caractères (avec branche) ─────────────────────────────────

    #[Test]
    #[DataProvider('provideValidBic11')]
    public function valid_bic_11_chars_passes(string $bic): void
    {
        $this->validator->validate($bic, new ValidBic());
        $this->assertNoViolation();
    }

    public static function provideValidBic11(): array
    {
        return [
            'BNP avec branche'       => ['BNPAFRPPXXX'],
            'Deutsche avec branche'  => ['DEUTDEDDBER'],
            'SocGen avec branche'    => ['SOGEFRPP01A'],
        ];
    }

    // ── BIC invalides ─────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideInvalidBic')]
    public function invalid_bic_raises_violation(string $bic): void
    {
        $this->validator->validate($bic, new ValidBic());

        $this->buildViolation((new ValidBic())->message)
            ->setParameter('{{ value }}', $bic)
            ->assertRaised();
    }

    public static function provideInvalidBic(): array
    {
        return [
            'trop court (7 chars)'      => ['BNPAFRP'],
            'trop long (12 chars)'      => ['BNPAFRPPXXXX'],
            'code pays numérique'       => ['BNPA12PP'],
            'caractères spéciaux'       => ['BNPA-FRP'],
            'code banque trop court'    => ['BNPFRPP'],
            '9 caractères invalide'     => ['BNPAFRPPX'],
            '10 caractères invalide'    => ['BNPAFRPPXX'],
        ];
    }

    // ── Insensibilité à la casse ──────────────────────────────────────────

    #[Test]
    public function lowercase_bic_is_valid(): void
    {
        $this->validator->validate('bnpafrpp', new ValidBic());
        $this->assertNoViolation();
    }

    #[Test]
    public function mixed_case_bic_is_valid(): void
    {
        $this->validator->validate('BnpAFRPP', new ValidBic());
        $this->assertNoViolation();
    }

    // ── Message d'erreur ─────────────────────────────────────────────────

    #[Test]
    public function violation_message_contains_invalid_value(): void
    {
        $this->validator->validate('INVALID', new ValidBic());

        $violations = $this->context->getViolations();
        self::assertCount(1, $violations);
        self::assertStringContainsString('INVALID', (string) $violations->get(0)->getMessage());
    }
}
