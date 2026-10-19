<?php

namespace App\Console\Commands;

use App\Models\MiseEnAvant;
use App\Services\CacheFil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reporte en base les impressions et clics des mises en avant cumulés en cache (CONTEXTE §14.9),
 * toutes les 5 minutes : une écriture groupée au lieu d'une écriture par affichage.
 */
class CompterMisesEnAvant extends Command
{
    protected $signature = 'mises-en-avant:compter';

    protected $description = 'Reporte en base les impressions et clics des mises en avant';

    public function handle(CacheFil $cache): int
    {
        $impressions = $cache->viderCompteursDifferes(CacheFil::COMPTEUR_IMPRESSIONS);
        $clics = $cache->viderCompteursDifferes(CacheFil::COMPTEUR_CLICS);

        DB::transaction(function () use ($impressions, $clics): void {
            foreach ($impressions as $id => $nombre) {
                MiseEnAvant::query()->whereKey($id)->incrementEach(['nb_impressions' => $nombre]);
            }

            foreach ($clics as $id => $nombre) {
                MiseEnAvant::query()->whereKey($id)->incrementEach(['nb_clics' => $nombre]);
            }
        });

        $this->info(array_sum($impressions).' impression(s), '.array_sum($clics).' clic(s) reportés.');

        return self::SUCCESS;
    }
}
