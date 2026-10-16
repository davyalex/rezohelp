<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Cycle de vie d'une publication.
 */
enum StatutPublication: string
{
    use AvecLibelles;

    case Brouillon = 'brouillon';
    case EnAttente = 'en_attente';
    case Publiee = 'publiee';
    case Rejetee = 'rejetee';
    case Expiree = 'expiree';
    case Cloturee = 'cloturee';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnAttente => 'En attente',
            self::Publiee => 'Publiée',
            self::Rejetee => 'Rejetée',
            self::Expiree => 'Expirée',
            self::Cloturee => 'Clôturée',
        };
    }
}
