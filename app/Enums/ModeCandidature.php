<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Comment postuler à une offre d'emploi.
 */
enum ModeCandidature: string
{
    use AvecLibelles;

    case Rezohelp = 'rezohelp';
    case Email = 'email';
    case Lien = 'lien';
    case Telephone = 'telephone';

    public function libelle(): string
    {
        return match ($this) {
            self::Rezohelp => 'Sur Rezohelp',
            self::Email => 'Par e-mail',
            self::Lien => 'Sur le site du recruteur',
            self::Telephone => 'Par téléphone',
        };
    }
}
