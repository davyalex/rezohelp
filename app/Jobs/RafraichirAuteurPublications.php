<?php

namespace App\Jobs;

use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recopie le nom affiché et l'avatar d'un membre sur ses publications en son nom propre
 * (champs dénormalisés du fil). Chaque enregistrement passe par l'observer, qui invalide le cache.
 */
class RafraichirAuteurPublications implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $utilisateurId) {}

    public function handle(): void
    {
        $utilisateur = Utilisateur::withTrashed()->find($this->utilisateurId);

        if ($utilisateur === null) {
            return;
        }

        $avatar = $utilisateur->getFirstMediaUrl('photo', 'avatar');

        Publication::query()
            ->where('auteur_id', $utilisateur->id)
            ->whereNull('entreprise_id')
            ->lazyById(200)
            ->each(function (Publication $publication) use ($utilisateur, $avatar): void {
                $publication->auteur_nom_affiche = $utilisateur->nomAffiche();
                $publication->auteur_avatar_url = $avatar !== '' ? $avatar : null;

                if ($publication->isDirty()) {
                    $publication->save();
                }
            });
    }
}
