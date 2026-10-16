<?php

namespace App\Actions\Publication;

use App\Models\Commentaire;
use App\Models\Publication;
use Illuminate\Support\Facades\DB;

/**
 * Supprime (soft delete) un commentaire et ses réponses, et met à jour le compteur.
 */
class SupprimerCommentaire
{
    public function executer(Commentaire $commentaire): void
    {
        DB::transaction(function () use ($commentaire): void {
            $reponses = $commentaire->sousCommentaires()->get();
            $nombre = 1 + $reponses->count();

            $reponses->each->delete();
            $commentaire->delete();

            Publication::query()->whereKey($commentaire->publication_id)->toBase()
                ->update(['nb_commentaires' => DB::raw('GREATEST(CAST(nb_commentaires AS SIGNED) - '.$nombre.', 0)')]);
        });
    }
}
