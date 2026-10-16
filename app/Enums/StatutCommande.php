<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Statut d'une commande.
 */
enum StatutCommande: string
{
    use AvecLibelles;

    case EnAttente = 'en_attente';
    case Payee = 'payee';
    case Echouee = 'echouee';
    case Annulee = 'annulee';
    case Remboursee = 'remboursee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Payee => 'Payée',
            self::Echouee => 'Échouée',
            self::Annulee => 'Annulée',
            self::Remboursee => 'Remboursée',
        };
    }
}
