<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\Constraint\ValidIban;
use App\Validator\Constraint\ValidIbanValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\ConstraintValidatorTestCase;

/**
 * Tests unitaires du validator IBAN (ISO 13616 — modulo 97).
 */
final class ValidIbanValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidIbanValidator
    {
        return new ValidIbanValidator();
    }

    // ── Cas valides ───────────────────────────────────────────────────────────

    #[Test]
    public function null_is_valid(): void
    {
        $this->validator->validate(null, new ValidIban());
        $this->assertNoViolation();
    }

    #[Test]
    public function empty_string_is_valid(): void
    {
        $this->validator->validate('', new ValidIban());
        $this->assertNoViolation();
    }

    #[Test]
    #[DataProvider('provideValidIbans')]
    public function valid_iban_passes(string $iban): void
    {
        $this->validator->validate($iban, new ValidIban());
        $this->assertNoViolation();
    }

    public static function provideValidIbans(): array
    {
        return [
            'france'       => ['FR7630006000011234567890189'],
            'france_spaces' => ['FR76 3000 6000 0112 3456 7890 189'],
            'allemagne'    => ['DE89370400440532013000'],
            'espagne'      => ['ES9121000418450200051332'],
            'belgique'     => ['BE68539007547034'],
            'luxembourg'   => ['LU280019400644750000'],
            'suisse'       => ['CH9300762011623852957'],
        ];
    }

    // ── Cas invalides ─────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('provideInvalidIbans')]
    public function invalid_iban_raises_violation(string $iban): void
    {
        $this->validator->validate($iban, new ValidIban());

        $this->buildViolation((new ValidIban())->message)
            ->setParameter('{{ value }}', $iban)
            ->assertRaised();
    }

    public static function provideInvalidIbans(): array
    {
        return [
            'trop court'           => ['FR76300'],
            'checksum invalide'    => ['FR7630006000011234567890188'], // dernier chiffre ±1
            'code pays inexistant' => ['XX0000000000000'],
            'caractères spéciaux' => ['FR76-3000-6000-01'],
        ];
    }

    #[Test]
    public function iban_lowercased_is_valid(): void
    {
        // L'IBAN doit être insensible à la casse
        $this->validator->validate('fr7630006000011234567890189', new ValidIban());
        $this->assertNoViolation();
    }
}
