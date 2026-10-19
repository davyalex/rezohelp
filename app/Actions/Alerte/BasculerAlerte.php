<?php

namespace App\Actions\Alerte;

use App\Enums\CleDroit;
use App\Models\Alerte;
use App\Services\Droits;

/**
 * Met une alerte en pause ou la reprend. Reprendre vérifie la limite alertes.max.
 */
class BasculerAlerte
{
    public function executer(Alerte $alerte): void
    {
        if (! $alerte->active) {
            $alerte->loadMissing('utilisateur');
            Droits::pour($alerte->utilisateur)->verifierOuEchouer(CleDroit::AlertesMax);
        }

        $alerte->active = ! $alerte->active;
        $alerte->save();
    }
}
