<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidBicValidator extends ConstraintValidator
{
    // Format BIC : 4 lettres (banque) + 2 lettres (pays) + 2 alphanum (ville) + 3 optionnels (branche)
    private const PATTERN = '/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidBic) {
            throw new UnexpectedTypeException($constraint, ValidBic::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $bic = strtoupper(preg_replace('/\\s/', '', (string) $value));

        if (!preg_match(self::PATTERN, $bic)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
