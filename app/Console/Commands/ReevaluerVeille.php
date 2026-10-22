<?php

namespace App\Console\Commands;

use App\Actions\Veille\ReevaluerAnnoncesVeille;
use Illuminate\Console\Command;

/**
 * Réexamine les annonces de la veille en attente après un changement des sources de la veille :
 * rattachement au domaine déclaré, puis mise en ligne si la source et le score le permettent.
 * Avec --renoter, recalcule d'abord en file le score des annonces avec le driver IA actuel.
 */
class ReevaluerVeille extends Command
{
    protected $signature = 'veille:reevaluer {--renoter : recalculer en file le score des annonces avec le driver IA actuel}';

    protected $description = 'Réévalue les annonces de la veille en attente selon les sources actuelles';

    public function handle(ReevaluerAnnoncesVeille $reevaluer): int
    {
        $bilan = $reevaluer->executer();

        $this->info($bilan['examinees'].' annonce(s) examinée(s), '.$bilan['rattachees'].' rattachée(s) à une autre source, '.$bilan['publiees'].' mise(s) en ligne.');

        if ($this->option('renoter')) {
            $nombre = $reevaluer->renoter();
            $this->info($nombre.' annonce(s) envoyée(s) à la notation (driver « '.config('rezohelp.ia.driver').' ») : lancez le traitement de la file pour appliquer les nouveaux scores.');
        }

        return self::SUCCESS;
    }
}
