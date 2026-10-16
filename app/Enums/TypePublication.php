<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type d'une publication du fil (CONTEXTE §7).
 */
enum TypePublication: string
{
    use AvecLibelles;

    case Emploi = 'emploi';
    case Service = 'service';
    case Formation = 'formation';

    public function libelle(): string
    {
        return match ($this) {
            self::Emploi => 'Emploi',
            self::Service => 'Home service',
            self::Formation => 'Formation',
        };
    }
}
