<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Visibilité d'un commentaire.
 */
enum StatutCommentaire: string
{
    use AvecLibelles;

    case Visible = 'visible';
    case Masque = 'masque';

    public function libelle(): string
    {
        return match ($this) {
            self::Visible => 'Visible',
            self::Masque => 'Masqué',
        };
    }
}
