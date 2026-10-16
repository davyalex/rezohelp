<?php

namespace App\Actions\Publication;

use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Ajoute ou retire une publication des « Enregistrés » d'un membre.
 */
class BasculerEnregistrement
{
    /**
     * @return bool true si la publication est désormais enregistrée
     */
    public function executer(Utilisateur $utilisateur, Publication $publication): bool
    {
        $retirees = $utilisateur->publicationsEnregistrees()->detach($publication->id);

        if ($retirees > 0) {
            return false;
        }

        $utilisateur->publicationsEnregistrees()->attach($publication->id, ['cree_le' => now()]);

        return true;
    }
}
