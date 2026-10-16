<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Nature d'une réponse à une publication (§11.8).
 */
enum TypeReponse: string
{
    use AvecLibelles;

    case Candidature = 'candidature';
    case Inscription = 'inscription';
    case Proposition = 'proposition';
    case Contact = 'contact';

    public function libelle(): string
    {
        return match ($this) {
            self::Candidature => 'Candidature',
            self::Inscription => 'Inscription',
            self::Proposition => 'Je suis disponible',
            self::Contact => 'Demande de contact',
        };
    }
}
