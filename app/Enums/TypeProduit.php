<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type d'un produit ponctuel (§14.4).
 */
enum TypeProduit: string
{
    use AvecLibelles;

    case OffreSupplementaire = 'offre_supplementaire';
    case Remontee = 'remontee';
    case UneCommune = 'une_commune';
    case SponsoriseFil = 'sponsorise_fil';

    public function libelle(): string
    {
        return match ($this) {
            self::OffreSupplementaire => 'Offre supplémentaire',
            self::Remontee => 'Remontée',
            self::UneCommune => 'À la une dans la commune',
            self::SponsoriseFil => 'Sponsorisé dans le fil',
        };
    }
}
