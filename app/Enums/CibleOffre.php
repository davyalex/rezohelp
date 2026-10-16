<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Public visé par un plan ou un produit (§14.2).
 */
enum CibleOffre: string
{
    use AvecLibelles;

    case Membre = 'membre';
    case Recruteur = 'recruteur';
    case Artisan = 'artisan';
    case Organisme = 'organisme';

    public function libelle(): string
    {
        return match ($this) {
            self::Membre => 'Membres',
            self::Recruteur => 'Recruteurs',
            self::Artisan => 'Artisans',
            self::Organisme => 'Organismes de formation',
        };
    }
}
