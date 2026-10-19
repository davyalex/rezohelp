<?php

namespace App\Actions\Admin;

use App\Enums\MotifRejet;
use App\Exceptions\OperationImpossibleException;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Doublon (CONTEXTE §13.3.B) : la publication est refusée avec le motif « doublon »
 * en citant l'originale ; le journal garde le lien entre les deux.
 */
class MarquerDoublon
{
    public function __construct(private readonly RejeterPublication $rejeter) {}

    public function executer(Publication $publication, Publication $originale, Utilisateur $moderateur): void
    {
        if ($originale->is($publication)) {
            throw new OperationImpossibleException('Une publication ne peut pas être le doublon d’elle-même.');
        }

        $this->rejeter->executer(
            $publication,
            $moderateur,
            MotifRejet::Doublon,
            'Doublon de « '.$originale->titre.' » (n° '.$originale->id.').',
            'publication.doublon',
        );
    }
}
