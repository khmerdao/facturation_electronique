<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidTvaIntraValidator extends ConstraintValidator
{
    // Patterns par pays (simplifiés)
    private const PATTERNS = [
        'FR' => '/^FR[0-9A-Z]{2}[0-9]{9}$/',
        'DE' => '/^DE[0-9]{9}$/',
        'ES' => '/^ES[A-Z0-9][0-9]{7}[A-Z0-9]$/',
        'IT' => '/^IT[0-9]{11}$/',
        'BE' => '/^BE0[0-9]{9}$/',
        'NL' => '/^NL[0-9]{9}B[0-9]{2}$/',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidTvaIntra) {
            throw new UnexpectedTypeException($constraint, ValidTvaIntra::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $vat = strtoupper(preg_replace('/\\s/', '', (string) $value));

        if (strlen($vat) < 4) {
            $this->addViolation($constraint, $value);
            return;
        }

        $country = substr($vat, 0, 2);
        $pattern = self::PATTERNS[$country] ?? '/^[A-Z]{2}[A-Z0-9]{2,12}$/';

        if (!preg_match($pattern, $vat)) {
            $this->addViolation($constraint, $value);
        }
    }

    private function addViolation(ValidTvaIntra $constraint, mixed $value): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
