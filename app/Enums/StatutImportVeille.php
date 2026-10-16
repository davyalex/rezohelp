<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Statut d'un import de fichier de veille.
 */
enum StatutImportVeille: string
{
    use AvecLibelles;

    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Erreur = 'erreur';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::Erreur => 'Erreur',
        };
    }
}
