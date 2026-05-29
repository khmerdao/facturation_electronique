<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DataSource;
use App\Repository\EReportingPaymentLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne de données de paiement d'un batch e-reporting. Pour les prestations
 * de services, la TVA est exigible à l'encaissement : chaque paiement
 * pertinent est rapporté ici, rattaché au paiement d'origine.
 */
#[ORM\Entity(repositoryClass: EReportingPaymentLineRepository::class)]
#[ORM\Table(name: 'ereporting_payment_lines')]
#[ORM\Index(name: 'idx_er_payment_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_er_payment_payment', columns: ['payment_id'])]
class EReportingPaymentLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: EReportingBatch::class, inversedBy: 'paymentLines')]
    #[ORM\JoinColumn(name: 'batch_id', nullable: false, onDelete: 'CASCADE')]
    private ?EReportingBatch $batch = null;

    /** Paiement à l'origine (nullable : peut être saisi manuellement). */
    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(name: 'payment_id', nullable: true, onDelete: 'SET NULL')]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $paymentDate;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $amountTtc;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $amountTva = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '20.00'])]
    private string $tvaRate = '20.00';

    #[ORM\Column(type: 'string', enumType: DataSource::class, options: ['default' => 'AUTO'])]
    private DataSource $source = DataSource::AUTO;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->paymentDate = new \DateTimeImmutable();
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

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;

        return $this;
    }

    public function getPaymentDate(): \DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): self
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getAmountTtc(): string
    {
        return $this->amountTtc;
    }

    public function setAmountTtc(string $amountTtc): self
    {
        $this->amountTtc = $amountTtc;

        return $this;
    }

    public function getAmountTva(): string
    {
        return $this->amountTva;
    }

    public function setAmountTva(string $amountTva): self
    {
        $this->amountTva = $amountTva;

        return $this;
    }

    public function getTvaRate(): string
    {
        return $this->tvaRate;
    }

    public function setTvaRate(string $tvaRate): self
    {
        $this->tvaRate = $tvaRate;

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
