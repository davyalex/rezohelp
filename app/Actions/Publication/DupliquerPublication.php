<?php

namespace App\Actions\Publication;

use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Support\IdentifiantsPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copie une publication en brouillon (détails et photos compris), pour une offre similaire.
 */
class DupliquerPublication
{
    private const array CHAMPS_COPIES = [
        'type', 'sous_type', 'titre', 'contenu', 'extrait', 'categorie_id', 'commune_id', 'auteur_id', 'entreprise_id',
        'auteur_nom_affiche', 'auteur_avatar_url', 'auteur_verifie', 'faits_cles', 'type_contrat', 'source',
    ];

    public function executer(Publication $origine): Publication
    {
        return DB::transaction(function () use ($origine): Publication {
            $origine->load(['detailEmploi', 'detailService', 'detailFormation', 'media']);

            $copie = new Publication($origine->only(self::CHAMPS_COPIES));
            $copie->fill([
                'uuid' => (string) Str::uuid(),
                'slug' => IdentifiantsPublication::slug($origine->titre),
                'code_court' => IdentifiantsPublication::codeCourt(),
                'expire_le' => now()->addDays(Parametre::entier('jours_expiration_defaut')),
            ]);
            $copie->statut = StatutPublication::Brouillon;
            $copie->save();

            $detail = $origine->detailEmploi ?? $origine->detailService ?? $origine->detailFormation;

            if ($detail !== null) {
                $detail->replicate()->fill(['publication_id' => $copie->id])->save();
            }

            foreach ($origine->getMedia('photos') as $photo) {
                $photo->copy($copie, 'photos');
            }

            return $copie;
        });
    }
}
