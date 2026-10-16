<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Statut d'un abonnement (§14.2).
 */
enum StatutAbonnement: string
{
    use AvecLibelles;

    case Essai = 'essai';
    case Actif = 'actif';
    case Grace = 'grace';
    case Expire = 'expire';
    case Resilie = 'resilie';

    public function libelle(): string
    {
        return match ($this) {
            self::Essai => 'Essai',
            self::Actif => 'Actif',
            self::Grace => 'Délai de grâce',
            self::Expire => 'Expiré',
            self::Resilie => 'Résilié',
        };
    }
}
