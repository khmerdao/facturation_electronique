<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Enum\InvoiceFormat;
use App\Service\Invoice\InvoiceCalculatorService;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdPaymentMeans;

/**
 * Génère le XML structuré d'une facture selon le format demandé.
 *
 * Formats supportés :
 *   - FACTURX / CII  : Cross Industry Invoice D16B via horstoeko/zugferd (profil EN16931)
 *   - UBL            : Universal Business Language 2.1 (génération manuelle DOM)
 *
 * Pré-requis : la facture doit être au statut VALIDATED ou supérieur
 * (numéro alloué, snapshot client rempli).
 */
final class InvoiceXmlBuilderService
{
    public function __construct(
        private readonly InvoiceCalculatorService $calculator,
    ) {}

    /**
     * Point d'entrée principal — dispatche selon le format configuré.
     *
     * @return string Contenu XML encodé UTF-8
     */
    public function build(Invoice $invoice): string
    {
        return match ($invoice->getFormat()) {
            InvoiceFormat::FACTURX, InvoiceFormat::CII => $this->buildCii($invoice),
            InvoiceFormat::UBL                         => $this->buildUbl($invoice),
        };
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // CII / Factur-X  (horstoeko/zugferd)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function buildCii(Invoice $invoice): string
    {
        $tenant  = $invoice->getTenant();
        $contact = $invoice->getContact();

        // Profil EN16931 (COMFORT) — conforme à la réglementation française
        $doc = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        // ── En-tête ──────────────────────────────────────────────────────────
        $doc->setDocumentInformation(
            documentNo:       $invoice->getNumber() ?? (string) $invoice->getId(),
            documentTypeCode: $invoice->getType()->ciiTypeCode(),
            date:             \DateTime::createFromImmutable($invoice->getIssueDate()),
            invoiceCurrency:  $invoice->getCurrency(),
        );

        if ($invoice->getDueDate()) {
            $doc->setDocumentPaymentTerms(
                description: null,
                dueDate:     \DateTime::createFromImmutable($invoice->getDueDate()),
            );
        }

        // ── Vendeur (émetteur = notre tenant) ────────────────────────────────
        $tenantAddr = $tenant->getAddress();

        $doc->setDocumentSeller(
            name:             $tenant->getName(),
            id:               null,
            description:      null,
        );

        $doc->addDocumentSellerTaxRegistration('VA', $tenant->getTvaIntra() ?? '');

        if ($tenant->getSiret()) {
            $doc->addDocumentSellerTaxRegistration('FC', $tenant->getSiret());
        }

        $doc->setDocumentSellerAddress(
            lineone:    $tenantAddr->getLine1() ?? '',
            linetwo:    $tenantAddr->getLine2() ?? '',
            linethree:  null,
            postcode:   $tenantAddr->getPostalCode() ?? '',
            city:       $tenantAddr->getCity() ?? '',
            country:    $tenantAddr->getCountry() ?? 'FR',
        );

        $doc->setDocumentSellerContact(
            personName:  null,
            orgUnit:     null,
            phoneNo:     $tenant->getPhone() ?? '',
            faxNo:       null,
            emailAddress: $tenant->getBillingEmail() ?? '',
        );

        // ── Acheteur (destinataire = client) ─────────────────────────────────
        $clientName = $invoice->getClientNameSnapshot() ?? $contact?->getName() ?? 'Client inconnu';

        $doc->setDocumentBuyer(
            name:        $clientName,
            id:          null,
            description: null,
        );

        if ($invoice->getClientSiretSnapshot()) {
            $doc->addDocumentBuyerTaxRegistration('FC', $invoice->getClientSiretSnapshot());
        }

        if ($contact) {
            $addr = $contact->getBillingAddress() ?? $contact->getAddress();
            $doc->setDocumentBuyerAddress(
                lineone:   $addr->getLine1() ?? '',
                linetwo:   $addr->getLine2() ?? '',
                linethree: null,
                postcode:  $addr->getPostalCode() ?? '',
                city:      $addr->getCity() ?? '',
                country:   $addr->getCountry() ?? 'FR',
            );
        }

        if ($invoice->getClientReference()) {
            $doc->setDocumentBuyerOrderReferencedDocument($invoice->getClientReference());
        }

        // ── Lignes ───────────────────────────────────────────────────────────
        foreach ($invoice->getLines() as $i => $line) {
            if ($line->isComment()) {
                continue;
            }

            $doc->addNewPosition((string) ($i + 1));
            $doc->setDocumentPositionProductDetails(
                name:        $line->getDescription(),
                description: null,
                sellerAssignedID: $line->getReference(),
            );
            $doc->setDocumentPositionQuantity(
                billedQuantity: (float) $line->getQuantity(),
                billedQuantityUnitCode: $this->mapUnit($line->getUnit()),
            );
            $doc->setDocumentPositionUnitPrice((float) $line->getUnitPrice());

            $tvaRate = (float) $line->getTvaRate();
            $exemptCode = $line->getTvaExemptionReason()?->ciiCode();

            $doc->addDocumentPositionTax(
                categoryCode: $tvaRate > 0 ? 'S' : ($exemptCode ? 'E' : 'Z'),
                typeCode:     'VAT',
                rateApplicablePercent: $tvaRate,
            );

            $doc->setDocumentPositionLineSummation(
                lineTotalAmount: (float) $line->getAmountHt(),
            );
        }

        // ── Totaux ───────────────────────────────────────────────────────────
        $breakdown = $this->calculator->getTvaBreakdown($invoice);

        foreach ($breakdown as $rate => $amounts) {
            $rateFloat = (float) $rate;
            $doc->addDocumentTax(
                categoryCode:          $rateFloat > 0 ? 'S' : 'Z',
                typeCode:              'VAT',
                basisAmount:           (float) $amounts['base'],
                calculatedAmount:      (float) $amounts['tva'],
                rateApplicablePercent: $rateFloat,
            );
        }

        $doc->setDocumentSummation(
            grandTotalAmount:         (float) $invoice->getTotalTtc(),
            duePayableAmount:         (float) $this->calculator->getRemainingDue($invoice),
            lineTotalAmount:          (float) $invoice->getTotalHt(),
            chargeTotalAmount:        0.0,
            allowanceTotalAmount:     0.0,
            taxBasisTotalAmount:      (float) $invoice->getTotalHt(),
            taxTotalAmount:           (float) $invoice->getTotalTva(),
            roundingAmount:           0.0,
            totalPrepaidAmount:       (float) $invoice->getAmountPaid(),
        );

        return $doc->getContent();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // UBL 2.1
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function buildUbl(Invoice $invoice): string
    {
        $tenant  = $invoice->getTenant();
        $contact = $invoice->getContact();
        $now     = (new \DateTimeImmutable())->format('Y-m-d');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $dom->appendChild($root);

        // En-tête
        $this->addUblEl($dom, $root, 'cbc:UBLVersionID', '2.1');
        $this->addUblEl($dom, $root, 'cbc:ID', $invoice->getNumber() ?? (string) $invoice->getId());
        $this->addUblEl($dom, $root, 'cbc:IssueDate', $invoice->getIssueDate()->format('Y-m-d'));
        $this->addUblEl($dom, $root, 'cbc:InvoiceTypeCode', $invoice->getType()->ublTypeCode());
        $this->addUblEl($dom, $root, 'cbc:DocumentCurrencyCode', $invoice->getCurrency());

        if ($invoice->getDueDate()) {
            $payTerms = $dom->createElement('cac:PaymentTerms');
            $this->addUblEl($dom, $payTerms, 'cbc:Note', 'Paiement à ' . $invoice->getDueDate()->format('d/m/Y'));
            $root->appendChild($payTerms);
        }

        // Vendeur
        $supplierParty = $dom->createElement('cac:AccountingSupplierParty');
        $party         = $dom->createElement('cac:Party');
        $partyName     = $dom->createElement('cac:PartyName');
        $this->addUblEl($dom, $partyName, 'cbc:Name', $tenant->getName());
        $party->appendChild($partyName);
        if ($tenant->getTvaIntra()) {
            $taxScheme = $dom->createElement('cac:PartyTaxScheme');
            $this->addUblEl($dom, $taxScheme, 'cbc:CompanyID', $tenant->getTvaIntra());
            $ts = $dom->createElement('cac:TaxScheme');
            $this->addUblEl($dom, $ts, 'cbc:ID', 'VAT');
            $taxScheme->appendChild($ts);
            $party->appendChild($taxScheme);
        }
        $supplierParty->appendChild($party);
        $root->appendChild($supplierParty);

        // Acheteur
        $customerParty = $dom->createElement('cac:AccountingCustomerParty');
        $cParty        = $dom->createElement('cac:Party');
        $cPartyName    = $dom->createElement('cac:PartyName');
        $clientName    = $invoice->getClientNameSnapshot() ?? $contact?->getName() ?? 'Client';
        $this->addUblEl($dom, $cPartyName, 'cbc:Name', $clientName);
        $cParty->appendChild($cPartyName);
        $customerParty->appendChild($cParty);
        $root->appendChild($customerParty);

        // Totaux TVA
        $breakdown = $this->calculator->getTvaBreakdown($invoice);
        foreach ($breakdown as $rate => $amounts) {
            $taxTotal = $dom->createElement('cac:TaxTotal');
            $taxAmt   = $dom->createElement('cbc:TaxAmount');
            $taxAmt->setAttribute('currencyID', $invoice->getCurrency());
            $taxAmt->textContent = $amounts['tva'];
            $taxTotal->appendChild($taxAmt);
            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $taxBase     = $dom->createElement('cbc:TaxableAmount');
            $taxBase->setAttribute('currencyID', $invoice->getCurrency());
            $taxBase->textContent = $amounts['base'];
            $taxSubtotal->appendChild($taxBase);
            $taxCat   = $dom->createElement('cac:TaxCategory');
            $taxPct   = $dom->createElement('cbc:Percent');
            $taxPct->textContent = $rate;
            $taxCat->appendChild($taxPct);
            $taxSubtotal->appendChild($taxCat);
            $taxTotal->appendChild($taxSubtotal);
            $root->appendChild($taxTotal);
        }

        // Totaux légaux
        $legalTotal = $dom->createElement('cac:LegalMonetaryTotal');
        $this->addUblMonetaryEl($dom, $legalTotal, 'cbc:LineExtensionAmount', $invoice->getTotalHt(), $invoice->getCurrency());
        $this->addUblMonetaryEl($dom, $legalTotal, 'cbc:TaxExclusiveAmount', $invoice->getTotalHt(), $invoice->getCurrency());
        $this->addUblMonetaryEl($dom, $legalTotal, 'cbc:TaxInclusiveAmount', $invoice->getTotalTtc(), $invoice->getCurrency());
        $this->addUblMonetaryEl($dom, $legalTotal, 'cbc:PayableAmount', $this->calculator->getRemainingDue($invoice), $invoice->getCurrency());
        $root->appendChild($legalTotal);

        // Lignes
        $pos = 1;
        foreach ($invoice->getLines() as $line) {
            if ($line->isComment()) {
                continue;
            }
            $invLine = $dom->createElement('cac:InvoiceLine');
            $this->addUblEl($dom, $invLine, 'cbc:ID', (string) $pos++);
            $invoicedQty = $dom->createElement('cbc:InvoicedQuantity');
            $invoicedQty->setAttribute('unitCode', $this->mapUnit($line->getUnit()));
            $invoicedQty->textContent = $line->getQuantity();
            $invLine->appendChild($invoicedQty);
            $this->addUblMonetaryEl($dom, $invLine, 'cbc:LineExtensionAmount', $line->getAmountHt(), $invoice->getCurrency());
            $item = $dom->createElement('cac:Item');
            $this->addUblEl($dom, $item, 'cbc:Description', $line->getDescription());
            $this->addUblEl($dom, $item, 'cbc:Name', $line->getDescription());
            $invLine->appendChild($item);
            $price = $dom->createElement('cac:Price');
            $this->addUblMonetaryEl($dom, $price, 'cbc:PriceAmount', $line->getUnitPrice(), $invoice->getCurrency());
            $invLine->appendChild($price);
            $root->appendChild($invLine);
        }

        return $dom->saveXML();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /** Mappe les codes unité internes vers UN/ECE Recommendation 20. */
    private function mapUnit(string $unit): string
    {
        return match (strtoupper($unit)) {
            'H'           => 'HUR',   // heure
            'J'           => 'DAY',   // jour
            'KG'          => 'KGM',   // kilogramme
            'KM'          => 'KMT',   // kilomètre
            'M'           => 'MTR',   // mètre
            'M2'          => 'MTK',   // mètre carré
            'M3'          => 'MTQ',   // mètre cube
            'L', 'LTR'    => 'LTR',   // litre
            'T'           => 'TNE',   // tonne
            default       => 'C62',   // unité (sans dimension)
        };
    }

    private function addUblEl(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): \DOMElement
    {
        $el              = $dom->createElement($tag);
        $el->textContent = $value;
        $parent->appendChild($el);

        return $el;
    }

    private function addUblMonetaryEl(\DOMDocument $dom, \DOMElement $parent, string $tag, string $amount, string $currency): \DOMElement
    {
        $el = $dom->createElement($tag);
        $el->setAttribute('currencyID', $currency);
        $el->textContent = $amount;
        $parent->appendChild($el);

        return $el;
    }
}
