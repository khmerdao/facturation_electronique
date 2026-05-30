<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueInvoiceNumber extends Constraint
{
    public string $message = 'Le numéro de facture "{{ value }}" est déjà utilisé pour ce tenant.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
