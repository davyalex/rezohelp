<?php

namespace App\Actions\Publication;

use App\Enums\MotifSignalement;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;

/**
 * Signalement d'une publication par un membre (une fois par publication).
 * Le masquage automatique est géré par SignalementObserver.
 */
class SignalerPublication
{
    public function executer(Utilisateur $utilisateur, Publication $publication, MotifSignalement $motif, ?string $commentaire): Signalement
    {
        return Signalement::query()->firstOrCreate(
            ['publication_id' => $publication->id, 'utilisateur_id' => $utilisateur->id],
            ['motif' => $motif, 'commentaire' => $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null],
        );
    }
}
