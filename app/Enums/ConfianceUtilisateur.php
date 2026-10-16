<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Niveau de confiance d'un compte (CONTEXTE §11.6).
 */
enum ConfianceUtilisateur: string
{
    use AvecLibelles;

    case Nouveau = 'nouveau';
    case Fiable = 'fiable';
    case Restreint = 'restreint';

    public function libelle(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::Fiable => 'Fiable',
            self::Restreint => 'Restreint',
        };
    }
}
