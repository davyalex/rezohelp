<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Raison d'un SMS envoyé (§7).
 */
enum MotifSms: string
{
    use AvecLibelles;

    case Alerte = 'alerte';
    case Recap = 'recap';
    case Otp = 'otp';
    case Essai = 'essai';
    case Relance = 'relance';

    public function libelle(): string
    {
        return match ($this) {
            self::Alerte => 'Alerte',
            self::Recap => 'Récapitulatif',
            self::Otp => 'Code de vérification',
            self::Essai => 'Essai',
            self::Relance => 'Relance',
        };
    }
}
