<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\CleDroit;
use App\Exceptions\OperationImpossibleException;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * K — Plans (CONTEXTE §14.10) : attributs et limites de toutes les clés CleDroit. Un seul plan
 * par défaut par cible. PlanObserver (sur Plan et PlanLimite) invalide les droits en cache :
 * l'effet est immédiat côté utilisateur.
 */
class EnregistrerPlan
{
    use JournaliseActivite;

    /**
     * @param  array<string, mixed>  $attributs
     * @param  array<string, string>  $limites  clé de droit => valeur ('-1' illimité ; clé absente = non fixée)
     */
    public function executer(Plan $plan, array $attributs, array $limites, Utilisateur $admin): Plan
    {
        $creation = ! $plan->exists;

        return DB::transaction(function () use ($plan, $attributs, $limites, $admin, $creation): Plan {
            $plan->fill($attributs);
            $differences = $this->differences($plan);

            // Un seul plan par défaut par cible (colonne unique cible_par_defaut) : l'ancien cède sa place d'abord.
            if ($plan->est_defaut) {
                Plan::query()->where('cible', $plan->cible->value)->where('est_defaut', true)
                    ->when($plan->exists, fn ($requete) => $requete->whereKeyNot($plan->id))
                    ->get()
                    ->each(fn (Plan $autre) => $autre->forceFill(['est_defaut' => false])->save());
            }

            $plan->save();

            $limitesAvant = $plan->limites()->pluck('valeur', 'cle')->map(fn ($v): string => (string) $v)->all();
            $this->synchroniserLimites($plan, $limites);

            $this->journaliser($admin, $creation ? 'plan.cree' : 'plan.modifie', $plan, [
                ...$differences,
                'limites_avant' => $limitesAvant,
                'limites_apres' => $limites,
            ]);

            return $plan;
        });
    }

    public function supprimer(Plan $plan, Utilisateur $admin): void
    {
        if ($plan->abonnements()->exists()) {
            throw new OperationImpossibleException('Ce plan a déjà été souscrit : désactivez-le plutôt que de le supprimer.');
        }

        if ($plan->est_defaut) {
            throw new OperationImpossibleException('Le plan par défaut d’une cible ne peut pas être supprimé.');
        }

        DB::transaction(function () use ($plan, $admin): void {
            $this->journaliser($admin, 'plan.supprime', $plan, ['code' => $plan->code]);
            $plan->limites()->get()->each(fn (PlanLimite $limite) => $limite->delete());
            $plan->delete();
        });
    }

    /**
     * Chaque ligne est enregistrée par le modèle (et non en masse) pour déclencher PlanObserver.
     *
     * @param  array<string, string>  $limites
     */
    private function synchroniserLimites(Plan $plan, array $limites): void
    {
        $existantes = $plan->limites()->get()->keyBy(fn (PlanLimite $limite): string => $limite->cle->value);

        foreach (CleDroit::cases() as $cle) {
            $valeur = $limites[$cle->value] ?? null;
            $ligne = $existantes->get($cle->value);

            if ($valeur === null) {
                $ligne?->delete();

                continue;
            }

            if ($ligne === null) {
                PlanLimite::query()->create(['plan_id' => $plan->id, 'cle' => $cle, 'valeur' => $valeur]);
            } elseif ((string) $ligne->valeur !== $valeur) {
                $ligne->valeur = $valeur;
                $ligne->save();
            }
        }
    }
}
