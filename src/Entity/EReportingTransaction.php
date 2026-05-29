<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DataSource;
use App\Entity\Enum\EReportingTransactionType;
use App\Repository\EReportingTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne de transaction d'un batch e-reporting (données de transaction).
 * Couvre les opérations hors champ e-invoicing : ventes B2C, ventes
 * intracom, exports, prestations à preneur étranger. La ventilation par
 * taux de TVA est stockée en JSON (amountHtByRate : {"20.00": "1000.00"}).
 */
#[ORM\Entity(repositoryClass: EReportingTransactionRepository::class)]
#[ORM\Table(name: 'ereporting_transactions')]
#[ORM\Index(name: 'idx_er_transaction_batch', columns: ['batch_id'])]
class EReportingTransaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: EReportingBatch::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'batch_id', nullable: false, onDelete: 'CASCADE')]
    private ?EReportingBatch $batch = null;

    /** Facture émise à l'origine (si applicable, ex : vente B2C facturée). */
    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'string', enumType: EReportingTransactionType::class)]
    private EReportingTransactionType $type;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $transactionDate;

    /** Ventilation HT par taux de TVA (JSON : {"20.00": "1000.00", ...}). */
    #[ORM\Column(type: 'json')]
    private array $amountHtByRate = [];

    /** Ventilation TVA par taux (JSON). */
    #[ORM\Column(type: 'json')]
    private array $amountTvaByRate = [];

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalTva = '0.00';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Nombre de transactions agrégées (mode synthèse quotidienne B2C). */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $transactionCount = 1;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(type: 'string', enumType: DataSource::class, options: ['default' => 'AUTO'])]
    private DataSource $source = DataSource::AUTO;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->transactionDate = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBatch(): ?EReportingBatch
    {
        return $this->batch;
    }

    public function setBatch(?EReportingBatch $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getType(): EReportingTransactionType
    {
        return $this->type;
    }

    public function setType(EReportingTransactionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTransactionDate(): \DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeImmutable $transactionDate): self
    {
        $this->transactionDate = $transactionDate;

        return $this;
    }

    public function getAmountHtByRate(): array
    {
        return $this->amountHtByRate;
    }

    public function setAmountHtByRate(array $amountHtByRate): self
    {
        $this->amountHtByRate = $amountHtByRate;

        return $this;
    }

    public function getAmountTvaByRate(): array
    {
        return $this->amountTvaByRate;
    }

    public function setAmountTvaByRate(array $amountTvaByRate): self
    {
        $this->amountTvaByRate = $amountTvaByRate;

        return $this;
    }

    public function getTotalHt(): string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): self
    {
        $this->totalHt = $totalHt;

        return $this;
    }

    public function getTotalTva(): string
    {
        return $this->totalTva;
    }

    public function setTotalTva(string $totalTva): self
    {
        $this->totalTva = $totalTva;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTransactionCount(): int
    {
        return $this->transactionCount;
    }

    public function setTransactionCount(int $transactionCount): self
    {
        $this->transactionCount = $transactionCount;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getSource(): DataSource
    {
        return $this->source;
    }

    public function setSource(DataSource $source): self
    {
        $this->source = $source;

        return $this;
    }
}
