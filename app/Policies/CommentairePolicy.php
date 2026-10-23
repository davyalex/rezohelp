<?php

namespace App\Policies;

use App\Models\Administrateur;
use App\Models\Commentaire;
use App\Models\Utilisateur;

/**
 * Un commentaire se supprime par son auteur, ou par l'équipe connectée au back-office
 * avec la permission commentaires.moderer (garde admin, D-066).
 */
class CommentairePolicy
{
    public function delete(Utilisateur|Administrateur $utilisateur, Commentaire $commentaire): bool
    {
        return $utilisateur instanceof Administrateur
            ? $utilisateur->can('commentaires.moderer')
            : $utilisateur->id === $commentaire->utilisateur_id;
    }
}
