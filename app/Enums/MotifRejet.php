<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Motifs prédéfinis de rejet en modération (§13.B).
 */
enum MotifRejet: string
{
    use AvecLibelles;

    case ArnaqueSuspectee = 'arnaque_suspectee';
    case Doublon = 'doublon';
    case Incomplete = 'incomplete';
    case HorsZone = 'hors_zone';
    case ContenuInapproprie = 'contenu_inapproprie';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::ArnaqueSuspectee => 'Arnaque suspectée',
            self::Doublon => 'Doublon',
            self::Incomplete => 'Annonce incomplète',
            self::HorsZone => 'Hors zone',
            self::ContenuInapproprie => 'Contenu inapproprié',
            self::Autre => 'Autre',
        };
    }
}
