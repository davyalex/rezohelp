<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Unité du tarif indicatif d'un service.
 */
enum UniteTarif: string
{
    use AvecLibelles;

    case Heure = 'heure';
    case Jour = 'jour';
    case Prestation = 'prestation';
    case Devis = 'devis';

    public function libelle(): string
    {
        return match ($this) {
            self::Heure => 'de l’heure',
            self::Jour => 'par jour',
            self::Prestation => 'la prestation',
            self::Devis => 'sur devis',
        };
    }
}
