<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ValidIban extends Constraint
{
    public string $message = 'L\'IBAN "{{ value }}" est invalide.';
}
