<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Ce qui est acheté par une commande.
 */
enum TypeArticle: string
{
    use AvecLibelles;

    case Plan = 'plan';
    case Produit = 'produit';

    public function libelle(): string
    {
        return match ($this) {
            self::Plan => 'Abonnement',
            self::Produit => 'Produit',
        };
    }
}
