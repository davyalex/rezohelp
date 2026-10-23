<?php

namespace App\Policies;

use App\Enums\StatutCommande;
use App\Models\Commande;
use App\Models\Utilisateur;

/**
 * Une commande et son reçu sont visibles par l'acheteur ; l'équipe passe par le back-office (D-066).
 */
class CommandePolicy
{
    public function view(Utilisateur $membre, Commande $commande): bool
    {
        return $commande->utilisateur_id === $membre->id;
    }

    public function voirRecu(Utilisateur $membre, Commande $commande): bool
    {
        return $this->view($membre, $commande)
            && $commande->numero_recu !== null
            && in_array($commande->statut, [StatutCommande::Payee, StatutCommande::Remboursee], true);
    }
}
