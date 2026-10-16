<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Issue d'un appel à l'API d'IA.
 */
enum StatutAppelIa: string
{
    use AvecLibelles;

    case Succes = 'succes';
    case Echec = 'echec';

    public function libelle(): string
    {
        return match ($this) {
            self::Succes => 'Succès',
            self::Echec => 'Échec',
        };
    }
}
