<?php

namespace App\Console\Commands;

use App\Actions\Veille\ReevaluerAnnoncesVeille;
use Illuminate\Console\Command;

/**
 * Réexamine les annonces de la veille en attente après un changement des sources de la veille :
 * rattachement au domaine déclaré, puis mise en ligne si la source et le score le permettent.
 */
class ReevaluerVeille extends Command
{
    protected $signature = 'veille:reevaluer';

    protected $description = 'Réévalue les annonces de la veille en attente selon les sources actuelles';

    public function handle(ReevaluerAnnoncesVeille $reevaluer): int
    {
        $bilan = $reevaluer->executer();

        $this->info($bilan['examinees'].' annonce(s) examinée(s), '.$bilan['rattachees'].' rattachée(s) à une autre source, '.$bilan['publiees'].' mise(s) en ligne.');

        return self::SUCCESS;
    }
}
