<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Models\Administrateur;
use App\Models\Publicite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Publicités (D-074) : création, modification, activation, suppression, toutes journalisées.
 * Après un changement d'image, le modèle est « touché » : ReferentielObserver vide alors
 * le cache des annonces (ref:publicites), qui contient les adresses des images.
 */
class GererPublicite
{
    use JournaliseActivite;

    /**
     * @param  array{titre: string, annonceur: string, lien: string, texte_alternatif: string, emplacement: string, debut_le: ?string, fin_le: ?string, active: bool, poids: int}  $donnees
     */
    public function enregistrer(
        Publicite $publicite,
        array $donnees,
        Administrateur $admin,
        ?UploadedFile $imagePrincipale = null,
        ?UploadedFile $imageSecondaire = null,
        bool $retirerSecondaire = false,
    ): Publicite {
        $creation = ! $publicite->exists;
        $publicite->fill($donnees);
        // Nouvel emplacement = nouveaux formats : l'ancienne image secondaire ne convient plus.
        $retirerSecondaire = $retirerSecondaire || (! $creation && $publicite->isDirty('emplacement'));

        return DB::transaction(function () use ($publicite, $admin, $creation, $imagePrincipale, $imageSecondaire, $retirerSecondaire): Publicite {
            $differences = $creation ? null : $this->differences($publicite);
            $publicite->save();

            $images = [];
            if ($imagePrincipale !== null) {
                $publicite->addMedia($imagePrincipale)->toMediaCollection(Publicite::IMAGE_PRINCIPALE);
                $images[] = 'principale';
            }
            if ($imageSecondaire !== null) {
                $publicite->addMedia($imageSecondaire)->toMediaCollection(Publicite::IMAGE_SECONDAIRE);
                $images[] = 'secondaire';
            } elseif ($retirerSecondaire) {
                $publicite->clearMediaCollection(Publicite::IMAGE_SECONDAIRE);
                $images[] = 'secondaire retirée';
            }

            if ($images !== []) {
                $publicite->touch();
            }

            $this->journaliser($admin, $creation ? 'publicite.creee' : 'publicite.modifiee', $publicite, array_filter([
                'emplacement' => $publicite->emplacement->value,
                'avant' => $differences['avant'] ?? null,
                'apres' => $differences['apres'] ?? null,
                'images' => $images ?: null,
            ]));

            return $publicite;
        });
    }

    public function basculer(Publicite $publicite, Administrateur $admin): void
    {
        DB::transaction(function () use ($publicite, $admin): void {
            $publicite->active = ! $publicite->active;
            $publicite->save();
            $this->journaliser($admin, $publicite->active ? 'publicite.activee' : 'publicite.desactivee', $publicite);
        });
    }

    public function supprimer(Publicite $publicite, Administrateur $admin): void
    {
        DB::transaction(function () use ($publicite, $admin): void {
            $this->journaliser($admin, 'publicite.supprimee', $publicite, [
                'titre' => $publicite->titre,
                'impressions' => $publicite->nb_impressions,
                'clics' => $publicite->nb_clics,
            ]);
            // Corbeille : les statistiques restent consultables en base ; les images sont gardées.
            $publicite->delete();
        });
    }
}
