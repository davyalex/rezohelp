<?php

namespace App\Listeners;

use App\Models\Utilisateur;
use Illuminate\Auth\Events\Login;

/**
 * Date de dernière connexion, affichée dans la fiche admin du membre (§13.F).
 */
class EnregistrerDerniereConnexion
{
    public function handle(Login $evenement): void
    {
        if ($evenement->user instanceof Utilisateur) {
            $evenement->user->forceFill(['derniere_connexion_le' => now()])->saveQuietly();
        }
    }
}
