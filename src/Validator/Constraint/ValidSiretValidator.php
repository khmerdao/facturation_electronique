<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidSiretValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSiret) {
            throw new UnexpectedTypeException($constraint, ValidSiret::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $siret = preg_replace('/[^0-9]/', '', (string) $value);

        if (strlen($siret) !== 14) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
            return;
        }

        // Algorithme de Luhn adapté au SIRET
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $digit = (int) $siret[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
        }

        if ($sum % 10 !== 0) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
