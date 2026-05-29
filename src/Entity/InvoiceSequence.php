<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TenantAwareTrait;
use App\Repository\InvoiceSequenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Séquence de numérotation. L'allocation se fait par lock pessimiste
 * (SELECT FOR UPDATE) dans InvoiceNumberingService pour garantir l'absence
 * de trou (art. 242 nonies A annexe II CGI). Verrouillée (locked=true) dès
 * la première facture émise : format et numéro de départ non modifiables.
 */
#[ORM\Entity(repositoryClass: InvoiceSequenceRepository::class)]
#[ORM\Table(name: 'invoice_sequences')]
#[ORM\Index(name: 'idx_sequence_tenant', columns: ['tenant_id'])]
class InvoiceSequence
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name = 'Séquence principale';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $prefix = 'FAC';

    /** AAAA | AA | null */
    #[ORM\Column(length: 4, nullable: true, options: ['default' => 'AAAA'])]
    private ?string $yearFormat = 'AAAA';

    #[ORM\Column(options: ['default' => false])]
    private bool $includeMonth = false;

    #[ORM\Column(length: 1, nullable: true, options: ['default' => '-'])]
    private ?string $separator = '-';

    #[ORM\Column(type: 'integer', options: ['default' => 4])]
    private int $padding = 4;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $startNumber = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $nextNumber = 1;

    #[ORM\Column(options: ['default' => false])]
    private bool $resetYearly = false;

    /** Année du dernier numéro alloué (pour la remise à zéro annuelle). */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lastYear = null;

    /** True dès qu'au moins une facture a été émise avec cette séquence. */
    #[ORM\Column(options: ['default' => false])]
    private bool $locked = false;

    /** Distingue la séquence avoirs de la séquence factures. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isCreditNoteSequence = false;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getYearFormat(): ?string
    {
        return $this->yearFormat;
    }

    public function setYearFormat(?string $yearFormat): self
    {
        $this->yearFormat = $yearFormat;

        return $this;
    }

    public function isIncludeMonth(): bool
    {
        return $this->includeMonth;
    }

    public function setIncludeMonth(bool $includeMonth): self
    {
        $this->includeMonth = $includeMonth;

        return $this;
    }

    public function getSeparator(): ?string
    {
        return $this->separator;
    }

    public function setSeparator(?string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function setPadding(int $padding): self
    {
        $this->padding = $padding;

        return $this;
    }

    public function getStartNumber(): int
    {
        return $this->startNumber;
    }

    public function setStartNumber(int $startNumber): self
    {
        $this->startNumber = $startNumber;

        return $this;
    }

    public function getNextNumber(): int
    {
        return $this->nextNumber;
    }

    public function setNextNumber(int $nextNumber): self
    {
        $this->nextNumber = $nextNumber;

        return $this;
    }

    public function isResetYearly(): bool
    {
        return $this->resetYearly;
    }

    public function setResetYearly(bool $resetYearly): self
    {
        $this->resetYearly = $resetYearly;

        return $this;
    }

    public function getLastYear(): ?int
    {
        return $this->lastYear;
    }

    public function setLastYear(?int $lastYear): self
    {
        $this->lastYear = $lastYear;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function isCreditNoteSequence(): bool
    {
        return $this->isCreditNoteSequence;
    }

    public function setIsCreditNoteSequence(bool $v): self
    {
        $this->isCreditNoteSequence = $v;

        return $this;
    }
}
