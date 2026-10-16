<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Origine d'un abonnement.
 */
enum OrigineAbonnement: string
{
    use AvecLibelles;

    case Essai = 'essai';
    case Paiement = 'paiement';
    case OffertAdmin = 'offert_admin';

    public function libelle(): string
    {
        return match ($this) {
            self::Essai => 'Essai gratuit',
            self::Paiement => 'Paiement',
            self::OffertAdmin => 'Offert par l’administration',
        };
    }
}
