<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Niveau de confiance d'une source de veille.
 */
enum NiveauConfiance: string
{
    use AvecLibelles;

    case Haut = 'haut';
    case Moyen = 'moyen';
    case Bas = 'bas';

    public function libelle(): string
    {
        return match ($this) {
            self::Haut => 'Haut',
            self::Moyen => 'Moyen',
            self::Bas => 'Bas',
        };
    }
}
