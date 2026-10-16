<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Statut d'un SMS.
 */
enum StatutSms: string
{
    use AvecLibelles;

    case EnFile = 'en_file';
    case Envoye = 'envoye';
    case Echoue = 'echoue';

    public function libelle(): string
    {
        return match ($this) {
            self::EnFile => 'En file',
            self::Envoye => 'Envoyé',
            self::Echoue => 'Échoué',
        };
    }
}
