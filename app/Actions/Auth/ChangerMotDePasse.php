<?php

namespace App\Actions\Auth;

use App\Models\Utilisateur;

/**
 * Changement de mot de passe par le membre (obligatoire après un mot de passe temporaire).
 */
class ChangerMotDePasse
{
    public function executer(Utilisateur $utilisateur, string $nouveauMotDePasse): void
    {
        $utilisateur->mot_de_passe = $nouveauMotDePasse;
        $utilisateur->forceFill(['doit_changer_mot_de_passe' => false])->save();
    }
}
