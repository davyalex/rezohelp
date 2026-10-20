<?php

namespace App\Console\Commands;

use App\Actions\Sms\PlanifierSms;
use App\Enums\CleDroit;
use App\Enums\MotifSms;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Récapitulatif SMS de 19h00 (CONTEXTE §14.8) : un seul SMS par membre pour les correspondances
 * qui n'ont pas eu de SMS parce que le plafond alertes.sms_par_jour était atteint.
 * Les correspondances sont soldées même si le membre a perdu le canal SMS entre-temps.
 */
class EnvoyerRecapitulatifSms extends Command
{
    protected $signature = 'alertes:recap-sms';

    protected $description = 'Envoie le récapitulatif SMS des alertes au-delà du plafond quotidien';

    public function handle(PlanifierSms $planifierSms): int
    {
        $envois = 0;

        $enAttente = DB::table('alertes_publications')
            ->join('alertes', 'alertes.id', '=', 'alertes_publications.alerte_id')
            ->where('alertes_publications.sms_en_attente', true)
            ->select(['alertes.utilisateur_id', DB::raw('COUNT(DISTINCT alertes_publications.publication_id) as total')])
            ->groupBy('alertes.utilisateur_id')
            ->pluck('total', 'utilisateur_id');

        Utilisateur::query()->whereKey($enAttente->keys())->lazyById(200)
            ->each(function (Utilisateur $membre) use ($enAttente, $planifierSms, &$envois): void {
                DB::table('alertes_publications')
                    ->whereIn('alerte_id', $membre->alertes()->select('id'))
                    ->where('sms_en_attente', true)
                    ->update(['sms_en_attente' => false]);

                if ($membre->telephone === null || $membre->telephone_verifie_le === null || $membre->estSuspendu()
                    || ! Droits::pour($membre)->peut(CleDroit::AlertesCanalSms)) {
                    return;
                }

                $total = (int) $enAttente[$membre->id];
                $lien = preg_replace('#^https?://#', '', route('espace.notifications'));

                $planifierSms->executer(
                    telephone: $membre->telephone,
                    message: "Rezohelp: {$total} autre(s) publication(s) correspondent a vos alertes aujourd'hui. Voir: {$lien}",
                    motif: MotifSms::Recap,
                    utilisateur: $membre,
                    respecterPlageSilencieuse: true,
                );
                $envois++;
            });

        $this->info("{$envois} récapitulatif(s) SMS envoyé(s).");

        return self::SUCCESS;
    }
}
