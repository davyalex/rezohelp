<?php

namespace App\Policies;

use App\Enums\CleDroit;
use App\Models\Alerte;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Auth\Access\Response;

class AlertePolicy
{
    /**
     * Créer ou réactiver une alerte : limite alertes.max du titulaire (§14.5).
     */
    public function create(Utilisateur $utilisateur): Response
    {
        $droits = Droits::pour($utilisateur);

        return $droits->restant(CleDroit::AlertesMax) !== 0
            ? Response::allow()
            : Response::deny('Vous avez atteint le nombre maximal d’alertes actives ('.$droits->limite(CleDroit::AlertesMax).'). Mettez-en une en pause ou supprimez-la.');
    }

    public function update(Utilisateur $utilisateur, Alerte $alerte): bool
    {
        return $utilisateur->id === $alerte->utilisateur_id;
    }

    public function delete(Utilisateur $utilisateur, Alerte $alerte): bool
    {
        return $utilisateur->id === $alerte->utilisateur_id;
    }
}
