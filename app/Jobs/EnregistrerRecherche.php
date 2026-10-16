<?php

namespace App\Jobs;

use App\Donnees\FiltresFil;
use App\Models\Recherche;
use App\Services\FilService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Journal des recherches, écrit en file pour ne pas ralentir la recherche (CONTEXTE §7).
 * Le nombre de résultats est compté ici, hors de la requête HTTP.
 */
class EnregistrerRecherche implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FiltresFil $filtres,
        public readonly ?int $utilisateurId,
    ) {}

    public function handle(FilService $fil): void
    {
        Recherche::query()->create([
            'terme' => mb_substr(mb_strtolower((string) $this->filtres->recherche), 0, 150),
            'type' => $this->filtres->type,
            'commune_id' => $this->filtres->communeId,
            'nb_resultats' => $fil->compter($this->filtres),
            'utilisateur_id' => $this->utilisateurId,
        ]);
    }
}
