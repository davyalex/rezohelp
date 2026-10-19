<?php

namespace App\Actions\Commande;

use App\Enums\TypeMiseEnAvant;
use App\Models\MiseEnAvant;
use App\Models\Publication;

/**
 * Une publication qui sort du fil (en attente, rejetée, expirée, clôturée, supprimée) n'est
 * jamais mise en avant : ses mises en avant sont suspendues, et reprennent à son retour avec
 * la durée qui leur restait (CONTEXTE §14.9). Appelée par PublicationObserver.
 */
class BasculerMisesEnAvant
{
    public function suspendre(Publication $publication): void
    {
        MiseEnAvant::query()
            ->where('publication_id', $publication->id)
            ->whereIn('type', [TypeMiseEnAvant::UneCommune->value, TypeMiseEnAvant::SponsoriseFil->value])
            ->whereNull('suspendue_le')
            ->where('fin_le', '>', now())
            ->update(['suspendue_le' => now()]);
    }

    public function reprendre(Publication $publication): void
    {
        MiseEnAvant::query()
            ->where('publication_id', $publication->id)
            ->whereNotNull('suspendue_le')
            ->get()
            ->each(function (MiseEnAvant $mise) use ($publication): void {
                // Remontée payée pendant que la publication était hors du fil : appliquée maintenant.
                if ($mise->type === TypeMiseEnAvant::Remontee) {
                    $publication->forceFill(['tri_le' => now()])->saveQuietly();
                    $mise->forceFill(['suspendue_le' => null, 'debut_le' => now(), 'fin_le' => now()])->save();

                    return;
                }

                // Durée qui restait au moment de la suspension (toute la durée si elle n'avait pas commencé).
                $suspendueLe = $mise->suspendue_le ?? now();
                $reference = $mise->debut_le?->max($suspendueLe) ?? $suspendueLe;
                $restant = max(0, (int) $reference->diffInSeconds($mise->fin_le ?? $reference, false));

                $mise->forceFill([
                    'suspendue_le' => null,
                    'debut_le' => $mise->debut_le?->min(now()) ?? now(),
                    'fin_le' => now()->addSeconds($restant),
                ])->save();
            });
    }
}
