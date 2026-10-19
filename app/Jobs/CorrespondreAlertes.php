<?php

namespace App\Jobs;

use App\Enums\FrequenceAlerte;
use App\Enums\StatutPublication;
use App\Models\Alerte;
use App\Models\Publication;
use App\Notifications\AlerteCorrespondante;
use App\Services\CorrespondanceAlertes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Cherche les alertes qui correspondent à une publication qui vient d'être publiée (CONTEXTE §10).
 * Chaque paire alerte / publication est journalisée une seule fois (alertes_publications) :
 * - alerte instantanée : notification immédiate ;
 * - alerte quotidienne : la paire attend le récapitulatif de 7h00.
 * Un membre n'est notifié qu'une fois par publication, même si plusieurs de ses alertes correspondent.
 */
class CorrespondreAlertes implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $publicationId) {}

    public function handle(CorrespondanceAlertes $correspondance): void
    {
        $publication = Publication::query()->find($this->publicationId);

        if ($publication === null || $publication->statut !== StatutPublication::Publiee) {
            return;
        }

        /** @var array<int, true> $dejaNotifies */
        $dejaNotifies = [];

        $correspondance->candidates($publication)
            ->with('utilisateur')
            ->lazyById(500)
            ->each(function (Alerte $alerte) use ($publication, $correspondance, &$dejaNotifies): void {
                if (! $correspondance->motsClesCorrespondent($alerte, $publication)) {
                    return;
                }

                $instantanee = $alerte->frequence === FrequenceAlerte::Instantanee;

                $inseree = DB::table('alertes_publications')->insertOrIgnore([
                    'alerte_id' => $alerte->id,
                    'publication_id' => $publication->id,
                    'notifie_le' => $instantanee ? now() : null,
                ]);

                if ($inseree === 0 || ! $instantanee) {
                    return;
                }

                $alerte->forceFill(['derniere_notification_le' => now()])->saveQuietly();

                if (! isset($dejaNotifies[$alerte->utilisateur_id])) {
                    $dejaNotifies[$alerte->utilisateur_id] = true;
                    $alerte->utilisateur->notify(new AlerteCorrespondante($publication, $alerte));
                }
            });
    }
}
