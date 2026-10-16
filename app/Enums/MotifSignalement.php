<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Motif d'un signalement.
 */
enum MotifSignalement: string
{
    use AvecLibelles;

    case Arnaque = 'arnaque';
    case Doublon = 'doublon';
    case Expiree = 'expiree';
    case Inapproprie = 'inapproprie';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Arnaque => 'Arnaque',
            self::Doublon => 'Doublon',
            self::Expiree => 'Offre expirée',
            self::Inapproprie => 'Contenu inapproprié',
            self::Autre => 'Autre',
        };
    }
}
