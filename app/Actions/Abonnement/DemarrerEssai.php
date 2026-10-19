<?php

namespace App\Actions\Abonnement;

use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\EssaiUtilise;
use App\Models\Plan;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\TelephoneService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Essai gratuit d'un plan (CONTEXTE §14.6) : sans paiement, en un clic, une seule fois
 * par numéro de téléphone (empreinte SHA-256) et par famille de plans, à vie.
 * Pour une entreprise, c'est le numéro du propriétaire qui compte.
 */
class DemarrerEssai
{
    public function __construct(private readonly TelephoneService $telephones) {}

    public function executer(Utilisateur $demandeur, Utilisateur|Entreprise $titulaire, Plan $plan): Abonnement
    {
        if (! $plan->actif || $plan->duree_essai_jours <= 0 || $plan->famille === null) {
            throw new OperationImpossibleException('Ce plan ne propose pas d’essai gratuit.');
        }

        if (! in_array($plan->cible, Droits::cibles($titulaire), true)) {
            throw new OperationImpossibleException('Ce plan ne correspond pas à ce compte.');
        }

        if ($titulaire instanceof Entreprise && $titulaire->proprietaire_id !== $demandeur->id) {
            throw new OperationImpossibleException('Seul le propriétaire de l’entreprise peut démarrer un essai.');
        }

        if ($demandeur->telephone === null || $demandeur->telephone_verifie_le === null) {
            throw new OperationImpossibleException('Vérifiez d’abord votre numéro de téléphone pour démarrer un essai.');
        }

        $empreinte = $this->telephones->empreinte($demandeur->telephone);

        if (EssaiUtilise::query()->where('empreinte_telephone', $empreinte)->where('famille', $plan->famille)->exists()) {
            throw new OperationImpossibleException('L’essai gratuit de cette formule a déjà été utilisé avec ce numéro.');
        }

        try {
            return DB::transaction(function () use ($titulaire, $plan, $empreinte): Abonnement {
                EssaiUtilise::query()->insert([
                    'empreinte_telephone' => $empreinte,
                    'famille' => $plan->famille,
                    'utilise_le' => now(),
                ]);

                return Abonnement::query()->create([
                    'titulaire_type' => $titulaire->getMorphClass(),
                    'titulaire_id' => $titulaire->getKey(),
                    'plan_id' => $plan->id,
                    'statut' => StatutAbonnement::Essai,
                    'origine' => OrigineAbonnement::Essai,
                    'debut_le' => now(),
                    'fin_le' => now()->addDays($plan->duree_essai_jours),
                ]);
            });
        } catch (QueryException) {
            // Deux demandes simultanées : la clé primaire (empreinte, famille) n'en laisse passer qu'une.
            throw new OperationImpossibleException('L’essai gratuit de cette formule a déjà été utilisé avec ce numéro.');
        }
    }
}
