<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Sort d'une ligne d'un fichier de la veille.
 */
enum StatutLigneVeille: string
{
    use AvecLibelles;

    case Importee = 'importee';
    case Doublon = 'doublon';
    case Rejetee = 'rejetee';

    public function libelle(): string
    {
        return match ($this) {
            self::Importee => 'Importée',
            self::Doublon => 'Doublon',
            self::Rejetee => 'Rejetée',
        };
    }
}
