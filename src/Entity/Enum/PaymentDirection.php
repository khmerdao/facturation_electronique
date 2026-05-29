<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PaymentDirection: string
{
    case INCOMING = 'INCOMING';   // Encaissement (facture émise)
    case OUTGOING = 'OUTGOING';   // Décaissement (facture reçue)
}
