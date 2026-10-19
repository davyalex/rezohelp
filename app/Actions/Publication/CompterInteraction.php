<?php

namespace App\Actions\Publication;

use App\Models\Publication;

/**
 * Incrémente un compteur de consultation (vues, partages) de façon atomique,
 * sans modifier modifie_le ni déclencher d'événement.
 */
class CompterInteraction
{
    public const string VUES = 'nb_vues';

    public const string PARTAGES = 'nb_partages';

    /** Clic vers un contact externe (lien, e-mail, téléphone) : compté comme une réponse (§11.8). */
    public const string REPONSES = 'nb_reponses';

    public function executer(int $publicationId, string $compteur): void
    {
        if (! in_array($compteur, [self::VUES, self::PARTAGES, self::REPONSES], true)) {
            return;
        }

        Publication::query()->whereKey($publicationId)->toBase()->increment($compteur);
    }
}
