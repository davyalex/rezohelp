<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Moyen par lequel une commande est réglée.
 */
enum PasserelleCommande: string
{
    use AvecLibelles;

    case Cinetpay = 'cinetpay';
    case Fake = 'fake';
    case Admin = 'admin';

    public function libelle(): string
    {
        return match ($this) {
            self::Cinetpay => 'CinetPay',
            self::Fake => 'Paiement simulé',
            self::Admin => 'Administration',
        };
    }
}
