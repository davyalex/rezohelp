<?php

namespace App\Actions\Admin;

use App\Enums\MotifRejet;
use App\Enums\StatutPublication;
use App\Models\Administrateur;
use App\Models\Publication;

/**
 * Actions groupées de la modération (CONTEXTE §13.3.B) : chaque publication passe par la même
 * action qu'à l'unité (événements, notifications, journal) ; celles qui ne sont plus en attente
 * sont ignorées.
 */
class ModererLot
{
    public function __construct(
        private readonly ValiderPublication $valider,
        private readonly RejeterPublication $rejeter,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return int nombre de publications traitées
     */
    public function executer(array $ids, string $action, Administrateur $moderateur, ?MotifRejet $motif = null, ?string $detail = null): int
    {
        $traitees = 0;

        Publication::query()
            ->whereKey($ids)
            ->where('statut', StatutPublication::EnAttente->value)
            ->get()
            ->each(function (Publication $publication) use ($action, $moderateur, $motif, $detail, &$traitees): void {
                if ($action === 'valider') {
                    $this->valider->executer($publication, $moderateur);
                } else {
                    $this->rejeter->executer($publication, $moderateur, $motif ?? MotifRejet::Autre, $detail);
                }

                $traitees++;
            });

        return $traitees;
    }
}
