<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ValidBic extends Constraint
{
    public string $message = 'Le code BIC/SWIFT "{{ value }}" est invalide.';
}
