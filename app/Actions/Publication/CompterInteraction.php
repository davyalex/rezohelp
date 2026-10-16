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

    public function executer(int $publicationId, string $compteur): void
    {
        if (! in_array($compteur, [self::VUES, self::PARTAGES], true)) {
            return;
        }

        Publication::query()->whereKey($publicationId)->toBase()->increment($compteur);
    }
}
