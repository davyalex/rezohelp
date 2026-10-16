<?php

namespace App\Policies;

use App\Models\Commentaire;
use App\Models\Utilisateur;

class CommentairePolicy
{
    public function delete(Utilisateur $utilisateur, Commentaire $commentaire): bool
    {
        return $utilisateur->id === $commentaire->utilisateur_id || $utilisateur->can('commentaires.moderer');
    }
}
