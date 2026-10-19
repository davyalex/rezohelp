<?php

namespace App\Console\Commands;

use App\Models\Parametre;
use App\Models\Publication;
use Illuminate\Console\Command;

/**
 * Purge définitive des publications supprimées depuis jours_purge_publications jours
 * (90 par défaut, CONTEXTE §13.3.C), planifiée chaque semaine. Les détails, réactions,
 * commentaires et médias suivent.
 */
class PurgerPublications extends Command
{
    protected $signature = 'publications:purger';

    protected $description = 'Supprime définitivement les publications supprimées depuis plus de jours_purge_publications jours';

    public function handle(): int
    {
        $purgees = 0;

        Publication::onlyTrashed()
            ->where('supprime_le', '<=', now()->subDays(Parametre::entier('jours_purge_publications')))
            ->lazyById(100)
            ->each(function (Publication $publication) use (&$purgees): void {
                $publication->forceDelete();
                $purgees++;
            });

        $this->info("{$purgees} publication(s) purgée(s).");

        return self::SUCCESS;
    }
}
