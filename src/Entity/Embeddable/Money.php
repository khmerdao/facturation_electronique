<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

/**
 * Objet valeur monétaire. Le montant est stocké en décimal (string Doctrine
 * pour éviter les imprécisions float), la devise en ISO 4217.
 * Embarqué dans les entités via #[ORM\Embedded] avec préfixe de colonne.
 */
#[ORM\Embeddable]
class Money
{
    #[ORM\Column(type: 'decimal', precision: 14, scale: 4, options: ['default' => '0.0000'])]
    private string $amount = '0.0000';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    public function __construct(string $amount = '0.0000', string $currency = 'EUR')
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

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

    public function asFloat(): float
    {
        return (float) $this->amount;
    }
}
