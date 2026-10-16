<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Modalité d'une formation.
 */
enum Modalite: string
{
    use AvecLibelles;

    case Presentiel = 'presentiel';
    case EnLigne = 'en_ligne';
    case Hybride = 'hybride';

    public function libelle(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::EnLigne => 'En ligne',
            self::Hybride => 'Hybride',
        };
    }
}
