<?php
declare(strict_types=1);
namespace App\Validator\Constraint;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueInvoiceNumberValidator extends ConstraintValidator
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueInvoiceNumber) {
            throw new UnexpectedTypeException($constraint, UniqueInvoiceNumber::class);
        }

        if (!$value instanceof Invoice) {
            return;
        }

        $number = $value->getNumber();
        if (null === $number) {
            return; // Brouillon sans numéro — pas de vérification
        }

        $existing = $this->invoiceRepository->findOneBy([
            'tenant' => $value->getTenant(),
            'number' => $number,
        ]);

        if ($existing && $existing->getId() !== $value->getId()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $number)
                ->atPath('number')
                ->addViolation();
        }
    }
}
