<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Administrateur;
use App\Models\Commande;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\Publication;
use Illuminate\Support\Facades\DB;

/**
 * F — Entreprises (CONTEXTE §13.3.F) : badge « Entreprise vérifiée » et fusion de deux fiches
 * en doublon. Les champs dénormalisés des cartes (nom, logo, vérification) suivent ; chaque
 * publication et chaque abonnement est enregistré un par un pour que les observers invalident
 * le cache du fil et des droits.
 */
class GererEntreprise
{
    use JournaliseActivite;

    public function basculerVerification(Entreprise $entreprise, Administrateur $admin): void
    {
        DB::transaction(function () use ($entreprise, $admin): void {
            $entreprise->verifiee = ! $entreprise->verifiee;
            $entreprise->save();

            Publication::query()->where('entreprise_id', $entreprise->id)->lazyById(200)
                ->each(function (Publication $publication) use ($entreprise): void {
                    $publication->auteur_verifie = $entreprise->verifiee;
                    $publication->save();
                });

            $this->journaliser($admin, $entreprise->verifiee ? 'entreprise.verifiee' : 'entreprise.verification_retiree', $entreprise);
        });
    }

    /**
     * Rattache à $conservee tout ce qui appartenait à $doublon (publications, abonnements,
     * crédits, commandes), puis supprime $doublon.
     */
    public function fusionner(Entreprise $doublon, Entreprise $conservee, Administrateur $admin): void
    {
        if ($doublon->is($conservee)) {
            throw new OperationImpossibleException('Choisissez deux fiches différentes.');
        }

        DB::transaction(function () use ($doublon, $conservee, $admin): void {
            $logo = $conservee->getFirstMediaUrl('logo', 'avatar');
            $publications = 0;

            Publication::withTrashed()->where('entreprise_id', $doublon->id)->lazyById(200)
                ->each(function (Publication $publication) use ($conservee, $logo, &$publications): void {
                    $publication->forceFill([
                        'entreprise_id' => $conservee->id,
                        'auteur_nom_affiche' => $conservee->nom,
                        'auteur_avatar_url' => $logo !== '' ? $logo : null,
                        'auteur_verifie' => $conservee->verifiee,
                    ])->save();
                    $publications++;
                });

            Abonnement::query()->where('titulaire_type', $doublon->getMorphClass())->where('titulaire_id', $doublon->id)->get()
                ->each(fn (Abonnement $abonnement) => $abonnement->forceFill(['titulaire_id' => $conservee->id])->save());

            foreach ([Credit::class, Commande::class] as $modele) {
                $modele::query()
                    ->where('titulaire_type', $doublon->getMorphClass())
                    ->where('titulaire_id', $doublon->id)
                    ->update(['titulaire_id' => $conservee->id]);
            }

            $doublon->delete();

            $this->journaliser($admin, 'entreprise.fusionnee', $conservee, [
                'doublon_id' => $doublon->id,
                'doublon_nom' => $doublon->nom,
                'publications' => $publications,
            ]);
        });
    }
}
