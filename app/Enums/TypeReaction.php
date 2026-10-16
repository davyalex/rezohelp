<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type de réaction à une publication.
 */
enum TypeReaction: string
{
    use AvecLibelles;

    case Jaime = 'jaime';

    public function libelle(): string
    {
        return match ($this) {
            self::Jaime => 'J’aime',
        };
    }
}
