<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ValidSiret extends Constraint
{
    public string $message = 'Le SIRET "{{ value }}" est invalide (algorithme de Luhn).';
}
