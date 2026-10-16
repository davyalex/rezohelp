<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Usage d'un code OTP envoyé par SMS.
 */
enum ObjetOtp: string
{
    use AvecLibelles;

    case Verification = 'verification';
    case Reinitialisation = 'reinitialisation';
    case ChangementNumero = 'changement_numero';

    public function libelle(): string
    {
        return match ($this) {
            self::Verification => 'Vérification du numéro',
            self::Reinitialisation => 'Réinitialisation du mot de passe',
            self::ChangementNumero => 'Changement de numéro',
        };
    }
}
