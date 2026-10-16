<?php

namespace App\Actions\Publication;

use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use Illuminate\Validation\ValidationException;

/**
 * Prolonge une publication en ligne de jours_prolongation jours, max_prolongations fois au plus.
 */
class ProlongerPublication
{
    public function executer(Publication $publication): void
    {
        if ($publication->statut !== StatutPublication::Publiee) {
            throw ValidationException::withMessages(['prolongation' => 'Seule une publication en ligne peut être prolongée.']);
        }

        if ($publication->nb_prolongations >= Parametre::entier('max_prolongations')) {
            throw ValidationException::withMessages([
                'prolongation' => 'Cette publication a déjà été prolongée '.Parametre::entier('max_prolongations').' fois. Dupliquez-la pour la republier.',
            ]);
        }

        $depart = $publication->expire_le !== null && $publication->expire_le->isFuture() ? $publication->expire_le : now();

        $publication->expire_le = $depart->copy()->addDays(Parametre::entier('jours_prolongation'));
        $publication->nb_prolongations++;
        $publication->save();
    }
}
