<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\InvoiceType;
use App\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Duplication de facture et création d'avoirs.
 */
final class InvoiceDuplicateService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceCalculatorService $calculatorService,
        private readonly InvoiceSequenceRepository $sequenceRepository,
    ) {}

    /**
     * Crée une copie d'une facture en statut DRAFT.
     *
     * Copié : lignes, contact, template, devise, objet, notes, format, délai paiement.
     * Non copié : numéro, statut, transmissions, paiements, fichiers S3, snapshot client.
     */
    public function duplicate(Invoice $source, User $actor): Invoice
    {
        $copy = new Invoice();
        $copy->setTenant($source->getTenant());
        $copy->setContact($source->getContact());
        $copy->setTemplate($source->getTemplate());
        $copy->setType($source->getType());
        $copy->setFormat($source->getFormat());
        $copy->setCurrency($source->getCurrency());
        $copy->setSubject($source->getSubject() ? '[Copie] ' . $source->getSubject() : null);
        $copy->setClientNotes($source->getClientNotes());
        $copy->setInternalNotes($source->getInternalNotes());
        $copy->setClientReference($source->getClientReference());
        $copy->setStatus(InvoiceStatus::DRAFT);
        $copy->setIssueDate(new \DateTimeImmutable());

        // Calculer la date d'échéance relative (+30j par défaut)
        if ($source->getDueDate() && $source->getIssueDate()) {
            $interval = $source->getIssueDate()->diff($source->getDueDate());
            $copy->setDueDate((new \DateTimeImmutable())->add($interval));
        }

        // Copier les lignes (valeurs figées, indépendantes du produit source)
        foreach ($source->getLines() as $sourceLine) {
            $line = $this->cloneLine($sourceLine, $copy);
            $copy->addLine($line);
            $this->em->persist($line);
        }

        $this->calculatorService->recalculate($copy);
        $this->em->persist($copy);

        return $copy;
    }

    /**
     * Crée un avoir à partir d'une facture acceptée (ACKNOWLEDGED ou PAID).
     *
     * L'avoir est lié à la facture d'origine via creditNoteFor.
     * Les montants sont identiques (positifs) — c'est le type CREDIT_NOTE
     * qui indique à horstoeko/zugferd d'inverser le sens.
     *
     * @throws \LogicException si la facture ne peut pas faire l'objet d'un avoir
     */
    public function createCreditNote(Invoice $original, User $actor, ?string $reason = null): Invoice
    {
        if (!$original->getStatus()->canIssueCreditNote()) {
            throw new \LogicException(sprintf(
                'Impossible d\'émettre un avoir sur une facture en statut "%s".',
                $original->getStatus()->value,
            ));
        }

        $creditNote = new Invoice();
        $creditNote->setTenant($original->getTenant());
        $creditNote->setContact($original->getContact());
        $creditNote->setTemplate($original->getTemplate());
        $creditNote->setType(InvoiceType::CREDIT_NOTE);
        $creditNote->setFormat($original->getFormat());
        $creditNote->setCurrency($original->getCurrency());
        $creditNote->setCreditNoteFor($original);
        $creditNote->setStatus(InvoiceStatus::DRAFT);
        $creditNote->setIssueDate(new \DateTimeImmutable());
        $creditNote->setSubject(
            $reason ?? sprintf('Avoir sur facture %s', $original->getNumber() ?? (string) $original->getId())
        );

        // Utiliser la séquence d'avoirs si elle existe
        $creditNoteSeq = $this->sequenceRepository->findDefaultForCreditNote($original->getTenant());
        if ($creditNoteSeq) {
            $creditNote->setSequence($creditNoteSeq);
        }

        // Copier les lignes
        foreach ($original->getLines() as $sourceLine) {
            $line = $this->cloneLine($sourceLine, $creditNote);
            $creditNote->addLine($line);
            $this->em->persist($line);
        }

        $this->calculatorService->recalculate($creditNote);
        $this->em->persist($creditNote);

        return $creditNote;
    }

    // ────────────────────────────────────────────────────────────────────────

    private function cloneLine(InvoiceLine $source, Invoice $newInvoice): InvoiceLine
    {
        $line = new InvoiceLine();
        $line->setInvoice($newInvoice);
        $line->setProduct($source->getProduct());
        $line->setPosition($source->getPosition());
        $line->setIsComment($source->isComment());
        $line->setReference($source->getReference());
        $line->setDescription($source->getDescription());
        $line->setQuantity($source->getQuantity());
        $line->setUnit($source->getUnit());
        $line->setUnitPrice($source->getUnitPrice());
        $line->setDiscount($source->getDiscount());
        $line->setTvaRate($source->getTvaRate());
        $line->setTvaExemptionReason($source->getTvaExemptionReason());
        // amountHt/amountTva recalculés par InvoiceCalculatorService::recalculate

        return $line;
    }
}
