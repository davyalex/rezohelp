<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Une annonce de service est une offre (artisan) ou une demande (client).
 */
enum SousTypeService: string
{
    use AvecLibelles;

    case Offre = 'offre';
    case Demande = 'demande';

    public function libelle(): string
    {
        return match ($this) {
            self::Offre => 'Je propose mes services',
            self::Demande => 'Je cherche un prestataire',
        };
    }
}
