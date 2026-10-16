<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type de contrat d'une offre d'emploi.
 */
enum TypeContrat: string
{
    use AvecLibelles;

    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Stage = 'stage';
    case Interim = 'interim';
    case Freelance = 'freelance';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Cdi => 'CDI',
            self::Cdd => 'CDD',
            self::Stage => 'Stage',
            self::Interim => 'Intérim',
            self::Freelance => 'Freelance',
            self::Autre => 'Autre',
        };
    }
}
