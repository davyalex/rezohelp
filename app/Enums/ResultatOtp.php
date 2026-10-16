<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Issue de la vérification d'un code OTP.
 */
enum ResultatOtp: string
{
    use AvecLibelles;

    case Valide = 'valide';
    case Invalide = 'invalide';
    case Expire = 'expire';
    case Bloque = 'bloque';

    /**
     * Message affiché sous le champ « code ».
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Valide => 'Code vérifié.',
            self::Invalide => 'Ce code est incorrect.',
            self::Expire => 'Ce code a expiré. Demandez-en un nouveau.',
            self::Bloque => 'Trop de tentatives avec ce code. Demandez-en un nouveau.',
        };
    }
}
