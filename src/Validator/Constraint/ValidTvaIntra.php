<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ValidTvaIntra extends Constraint
{
    public string $message = 'Le numéro de TVA intracommunautaire "{{ value }}" est invalide.';

    /** Appeler le service VIES pour vérification en ligne. */
    public bool $checkVies = false;
}
