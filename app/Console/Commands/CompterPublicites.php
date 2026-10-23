<?php

namespace App\Console\Commands;

use App\Models\Publicite;
use App\Services\CacheFil;
use App\Services\Publicites;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reporte en base les impressions et clics des publicités cumulés en cache (D-074), toutes les 5 min.
 */
class CompterPublicites extends Command
{
    protected $signature = 'publicites:compter';

    protected $description = 'Reporte en base les impressions et clics des publicités';

    public function handle(CacheFil $cache): int
    {
        $impressions = $cache->viderCompteursDifferes(Publicites::COMPTEUR_IMPRESSIONS);
        $clics = $cache->viderCompteursDifferes(Publicites::COMPTEUR_CLICS);

        DB::transaction(function () use ($impressions, $clics): void {
            foreach ($impressions as $id => $nombre) {
                Publicite::withTrashed()->whereKey($id)->incrementEach(['nb_impressions' => $nombre]);
            }

            foreach ($clics as $id => $nombre) {
                Publicite::withTrashed()->whereKey($id)->incrementEach(['nb_clics' => $nombre]);
            }
        });

        $this->info(array_sum($impressions).' impression(s), '.array_sum($clics).' clic(s) reportés.');

        return self::SUCCESS;
    }
}
