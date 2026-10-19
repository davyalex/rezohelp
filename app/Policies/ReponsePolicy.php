<?php

namespace App\Policies;

use App\Models\Reponse;
use App\Models\Utilisateur;

class ReponsePolicy
{
    /**
     * Seul l'auteur de la publication marque une réponse vue, retenue ou refusée.
     */
    public function update(Utilisateur $utilisateur, Reponse $reponse): bool
    {
        return $reponse->publication()->where('auteur_id', $utilisateur->id)->exists();
    }
}
