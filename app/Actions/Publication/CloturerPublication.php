<?php

namespace App\Actions\Publication;

use App\Enums\StatutPublication;
use App\Models\Publication;
use Illuminate\Validation\ValidationException;

/**
 * « Poste pourvu », « Plus disponible », « Complet » : la publication quitte le fil,
 * sa page reste visible avec un bandeau (CONTEXTE §11.7).
 */
class CloturerPublication
{
    public function executer(Publication $publication, string $motif): void
    {
        if (! in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::EnAttente], true)) {
            throw ValidationException::withMessages(['motif_cloture' => 'Seule une publication en ligne ou en attente peut être clôturée.']);
        }

        $publication->statut = StatutPublication::Cloturee;
        $publication->motif_cloture = $motif;
        $publication->save();
    }
}
