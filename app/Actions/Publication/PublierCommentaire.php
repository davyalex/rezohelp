<?php

namespace App\Actions\Publication;

use App\Enums\StatutCommentaire;
use App\Models\Commentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Publie un commentaire, ou une réponse à un commentaire (un seul niveau).
 */
class PublierCommentaire
{
    public function executer(Utilisateur $auteur, Publication $publication, string $contenu, ?Commentaire $parent = null): Commentaire
    {
        return DB::transaction(function () use ($auteur, $publication, $contenu, $parent): Commentaire {
            $commentaire = new Commentaire([
                'publication_id' => $publication->id,
                'utilisateur_id' => $auteur->id,
                // Une réponse à une réponse est rattachée au commentaire d'origine.
                'parent_id' => $parent !== null ? ($parent->parent_id ?? $parent->id) : null,
                'contenu' => trim($contenu),
            ]);
            $commentaire->statut = StatutCommentaire::Visible;
            $commentaire->save();

            Publication::query()->whereKey($publication->id)->toBase()->increment('nb_commentaires');

            return $commentaire;
        });
    }
}
