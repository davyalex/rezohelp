<?php

namespace App\Actions\Publication;

use App\Enums\StatutReponse;
use App\Models\Reponse;
use App\Notifications\StatutReponseModifie;

/**
 * L'auteur marque une réponse vue, retenue ou refusée ; le répondant est prévenu.
 */
class ChangerStatutReponse
{
    public function executer(Reponse $reponse, StatutReponse $statut): void
    {
        if ($reponse->statut === $statut) {
            return;
        }

        $reponse->statut = $statut;
        $reponse->save();

        $reponse->loadMissing(['publication:id,titre,slug', 'utilisateur']);
        $reponse->utilisateur->notify(new StatutReponseModifie($reponse));
    }
}
