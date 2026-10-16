<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Fréquence d'envoi d'une alerte.
 */
enum FrequenceAlerte: string
{
    use AvecLibelles;

    case Instantanee = 'instantanee';
    case Quotidienne = 'quotidienne';

    public function libelle(): string
    {
        return match ($this) {
            self::Instantanee => 'Dès la publication',
            self::Quotidienne => 'Une fois par jour (7 h)',
        };
    }
}
