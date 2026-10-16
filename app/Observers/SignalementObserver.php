<?php

namespace App\Observers;

use App\Enums\MotifSignalement;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use App\Notifications\PublicationMasquee;
use Illuminate\Support\Facades\Notification;

/**
 * Masquage automatique (CONTEXTE §13.D) : à seuil_masquage_signalements signalements
 * « arnaque » non traités, la publication repasse en attente et les modérateurs
 * sont prévenus.
 */
class SignalementObserver
{
    public function created(Signalement $signalement): void
    {
        if ($signalement->motif !== MotifSignalement::Arnaque) {
            return;
        }

        $publication = Publication::query()->find($signalement->publication_id);

        if ($publication === null || $publication->statut !== StatutPublication::Publiee) {
            return;
        }

        $nombre = Signalement::query()
            ->where('publication_id', $publication->id)
            ->where('motif', MotifSignalement::Arnaque->value)
            ->whereNull('traite_le')
            ->count();

        if ($nombre < Parametre::entier('seuil_masquage_signalements')) {
            return;
        }

        $publication->statut = StatutPublication::EnAttente;
        $publication->save();

        Notification::send(
            Utilisateur::permission('publications.moderer')->get(),
            new PublicationMasquee($publication, $nombre),
        );
    }
}
