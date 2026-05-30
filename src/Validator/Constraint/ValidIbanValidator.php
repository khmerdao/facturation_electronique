<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidIbanValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidIban) {
            throw new UnexpectedTypeException($constraint, ValidIban::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $iban = strtoupper(preg_replace('/\\s/', '', (string) $value));

        // Longueur minimale et format
        if (strlen($iban) < 15 || !ctype_alnum($iban)) {
            $this->addViolation($constraint, $value);
            return;
        }

        // Déplacer les 4 premiers caractères à la fin
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Remplacer les lettres par des chiffres (A=10, B=11, …)
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string)(ord($char) - 55) : $char;
        }

        // Vérifier modulo 97
        $remainder = 0;
        foreach (str_split($numeric, 9) as $chunk) {
            $remainder = (int)(($remainder . $chunk) % 97);
        }

        if ($remainder !== 1) {
            $this->addViolation($constraint, $value);
        }
    }

    private function addViolation(ValidIban $constraint, mixed $value): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
