<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Suivi d'une réponse par l'auteur de la publication.
 */
enum StatutReponse: string
{
    use AvecLibelles;

    case Envoyee = 'envoyee';
    case Vue = 'vue';
    case Retenue = 'retenue';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::Envoyee => 'Envoyée',
            self::Vue => 'Vue',
            self::Retenue => 'Retenue',
            self::Refusee => 'Refusée',
        };
    }
}
