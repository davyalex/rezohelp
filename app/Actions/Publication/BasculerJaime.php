<?php

namespace App\Actions\Publication;

use App\Enums\TypeReaction;
use App\Models\Publication;
use App\Models\Reaction;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute ou retire le « J'aime » d'un membre. Le compteur nb_jaime est modifié
 * de façon atomique, sans toucher à modifie_le : la carte en cache reste valide.
 */
class BasculerJaime
{
    /**
     * @return array{aime: bool, nb_jaime: int}
     */
    public function executer(Utilisateur $utilisateur, Publication $publication): array
    {
        return DB::transaction(function () use ($utilisateur, $publication): array {
            $supprimees = Reaction::query()
                ->where('publication_id', $publication->id)
                ->where('utilisateur_id', $utilisateur->id)
                ->delete();

            if ($supprimees > 0) {
                Publication::query()->whereKey($publication->id)->where('nb_jaime', '>', 0)->toBase()->decrement('nb_jaime');
                $aime = false;
            } else {
                Reaction::query()->insertOrIgnore([
                    'publication_id' => $publication->id,
                    'utilisateur_id' => $utilisateur->id,
                    'type' => TypeReaction::Jaime->value,
                    'cree_le' => now(),
                ]);
                Publication::query()->whereKey($publication->id)->toBase()->increment('nb_jaime');
                $aime = true;
            }

            return [
                'aime' => $aime,
                'nb_jaime' => (int) Publication::query()->whereKey($publication->id)->value('nb_jaime'),
            ];
        });
    }
}
