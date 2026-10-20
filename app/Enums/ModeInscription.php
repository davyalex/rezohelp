<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Comment s'inscrire à une formation.
 */
enum ModeInscription: string
{
    use AvecLibelles;

    case Rezohelp = 'rezohelp';
    case Lien = 'lien';
    case Telephone = 'telephone';

    public function libelle(): string
    {
        return match ($this) {
            self::Rezohelp => 'Sur Rezohelp',
            self::Lien => 'Sur le site de l’organisme',
            self::Telephone => 'Par téléphone',
        };
    }
}
