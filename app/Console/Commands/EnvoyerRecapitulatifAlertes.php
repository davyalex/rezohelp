<?php

namespace App\Console\Commands;

use App\Enums\FrequenceAlerte;
use App\Enums\StatutPublication;
use App\Models\Alerte;
use App\Notifications\RecapitulatifAlertes;
use App\Services\CorrespondanceAlertes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Récapitulatif des alertes quotidiennes (planifié à 7h00, CONTEXTE §10) : une notification
 * par alerte avec les publications rapprochées depuis la veille et encore en ligne.
 */
class EnvoyerRecapitulatifAlertes extends Command
{
    protected $signature = 'alertes:recap-quotidien';

    protected $description = 'Envoie le récapitulatif quotidien des alertes';

    private const int TITRES_AFFICHES = 5;

    public function handle(CorrespondanceAlertes $correspondance): int
    {
        $envois = 0;

        Alerte::query()
            ->where('active', true)
            ->where('frequence', FrequenceAlerte::Quotidienne->value)
            ->whereHas('publications', fn ($requete) => $requete->whereNull('alertes_publications.notifie_le'))
            ->with('utilisateur')
            ->lazyById(200)
            ->each(function (Alerte $alerte) use ($correspondance, &$envois): void {
                $enAttente = DB::table('alertes_publications')
                    ->join('publications', 'publications.id', '=', 'alertes_publications.publication_id')
                    ->where('alertes_publications.alerte_id', $alerte->id)
                    ->whereNull('alertes_publications.notifie_le')
                    ->select(['publications.id', 'publications.titre', 'publications.statut', 'publications.supprime_le'])
                    ->orderByDesc('publications.tri_le')
                    ->get();

                $enLigne = $enAttente->filter(fn (object $ligne): bool => $ligne->statut === StatutPublication::Publiee->value && $ligne->supprime_le === null);

                // Toutes les paires sont soldées, même celles des publications retirées entre-temps.
                DB::table('alertes_publications')
                    ->where('alerte_id', $alerte->id)
                    ->whereIn('publication_id', $enAttente->pluck('id'))
                    ->update(['notifie_le' => now()]);

                if ($enLigne->isEmpty() || $alerte->utilisateur->estSuspendu()) {
                    return;
                }

                $alerte->utilisateur->notify(new RecapitulatifAlertes(
                    $alerte,
                    $enLigne->count(),
                    $enLigne->take(self::TITRES_AFFICHES)->pluck('titre')->map(fn ($titre): string => (string) $titre)->values()->all(),
                    $correspondance->urlFil($alerte),
                ));
                $alerte->forceFill(['derniere_notification_le' => now()])->saveQuietly();
                $envois++;
            });

        $this->info("{$envois} récapitulatif(s) envoyé(s).");

        return self::SUCCESS;
    }
}
