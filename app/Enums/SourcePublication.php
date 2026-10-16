<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Origine d'une publication.
 */
enum SourcePublication: string
{
    use AvecLibelles;

    case Utilisateur = 'utilisateur';
    case VeilleIa = 'veille_ia';

    public function libelle(): string
    {
        return match ($this) {
            self::Utilisateur => 'Membre',
            self::VeilleIa => 'Veille IA',
        };
    }
}
