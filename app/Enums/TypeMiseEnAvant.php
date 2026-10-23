<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type de mise en avant d'une publication (§14.9).
 */
enum TypeMiseEnAvant: string
{
    use AvecLibelles;

    case Remontee = 'remontee';
    case UneCommune = 'une_commune';
    case SponsoriseFil = 'sponsorise_fil';

    public function libelle(): string
    {
        return match ($this) {
            self::Remontee => 'Remontée',
            self::UneCommune => 'À la une',
            self::SponsoriseFil => 'Annonce sponsorisée',
        };
    }
}
