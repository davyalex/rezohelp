<?php

namespace App\Services;

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Enums\TypeProduit;
use App\Models\Entreprise;
use App\Models\EssaiUtilise;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Produit;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ce que l'on peut proposer à un titulaire (CONTEXTE §14.11) : plans à acheter, essais
 * encore disponibles, produits ponctuels. Tout est lu en base : un plan ou un produit
 * désactivé dans l'admin disparaît de toutes les incitations.
 */
class Offres
{
    public function __construct(private readonly TelephoneService $telephones) {}

    /**
     * Plans visibles sur /tarifs, groupés par cible.
     *
     * @return array<string, Collection<int, Plan>>
     */
    public function grillePublique(): array
    {
        return Plan::query()
            ->where('actif', true)->where('visible', true)
            ->with('limites')
            ->orderBy('ordre')->orderBy('prix_fcfa')
            ->get()
            ->groupBy(fn (Plan $plan): string => $plan->cible->value)
            ->all();
    }

    /**
     * @return Collection<int, Produit>
     */
    public function produitsActifs(): Collection
    {
        return Produit::query()->where('actif', true)->orderBy('ordre')->orderBy('prix_fcfa')->get();
    }

    /**
     * Plans payants que ce titulaire peut acheter.
     *
     * @return Collection<int, Plan>
     */
    public function plansAchetables(Utilisateur|Entreprise $titulaire): Collection
    {
        return Plan::query()
            ->where('actif', true)->where('visible', true)->where('prix_fcfa', '>', 0)
            ->whereIn('cible', array_map(fn (CibleOffre $cible): string => $cible->value, Droits::cibles($titulaire)))
            ->with('limites')
            ->orderBy('ordre')->orderBy('prix_fcfa')
            ->get();
    }

    /**
     * L'essai de ce plan n'a pas encore été utilisé avec le numéro du demandeur.
     */
    public function essaiDisponible(Utilisateur $demandeur, Plan $plan): bool
    {
        if (! $plan->actif || $plan->duree_essai_jours <= 0 || $plan->famille === null || $demandeur->telephone === null) {
            return false;
        }

        return ! EssaiUtilise::query()
            ->where('empreinte_telephone', $this->telephones->empreinte($demandeur->telephone))
            ->where('famille', $plan->famille)
            ->exists();
    }

    /**
     * Premier plan à l'essai qui améliorerait la clé $cle pour ce titulaire (incitations contextuelles).
     */
    public function essaiPour(Utilisateur $demandeur, Utilisateur|Entreprise $titulaire, CleDroit $cle): ?Plan
    {
        $actuelle = Droits::pour($titulaire)->limite($cle);

        if ($actuelle === CleDroit::ILLIMITE) {
            return null;
        }

        return $this->plansAchetables($titulaire)
            ->filter(fn (Plan $plan): bool => $this->ameliore($plan, $cle, $actuelle))
            ->first(fn (Plan $plan): bool => $this->essaiDisponible($demandeur, $plan));
    }

    /**
     * Produit actif d'un type donné pour la cible concernée (ex. offre_supplementaire pour un recruteur).
     */
    public function produit(TypeProduit $type, ?CibleOffre $cible = null): ?Produit
    {
        return Produit::query()
            ->where('actif', true)
            ->where('type', $type->value)
            ->when($cible !== null, fn ($requete) => $requete->where('cible', $cible?->value))
            ->orderBy('ordre')->orderBy('prix_fcfa')
            ->first();
    }

    /**
     * Options de l'écran « Quota atteint » (§14.11), pour chaque titulaire possible de la publication :
     * acheter une offre à l'unité (emploi) ou essayer une formule qui relève la limite.
     *
     * @param  iterable<Utilisateur|Entreprise>  $titulaires
     * @return list<array{nom: string, entreprise_id: ?int, produit: ?Produit, essai: ?Plan}>
     */
    public function pourQuotaAtteint(Utilisateur $membre, iterable $titulaires, CleDroit $cle): array
    {
        $produit = $cle === CleDroit::EmploiOffresMois ? $this->produit(TypeProduit::OffreSupplementaire) : null;
        $options = [];

        foreach ($titulaires as $titulaire) {
            $entreprise = $titulaire instanceof Entreprise;
            $option = [
                'nom' => $entreprise ? $titulaire->nom : 'En mon nom (particulier)',
                'entreprise_id' => $entreprise ? (int) $titulaire->id : null,
                // Une offre à l'unité vaut pour une entreprise comme pour un particulier qui recrute.
                'produit' => $produit,
                'essai' => $this->essaiPour($membre, $titulaire, $cle),
            ];

            if ($option['produit'] !== null || $option['essai'] !== null) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * Compteurs de « Mon abonnement » (« 2 / 3 offres ce mois », « SMS : 3 / 5 aujourd'hui »).
     *
     * @return list<array{cle: CleDroit, consomme: int, limite: int, credits: int}>
     */
    public function compteurs(Utilisateur|Entreprise $titulaire): array
    {
        $droits = Droits::pour($titulaire);
        $cles = $titulaire instanceof Entreprise
            ? [CleDroit::EmploiOffresMois, CleDroit::FormationOffresActives, CleDroit::PublicationRemonteesMois]
            : [CleDroit::AlertesMax, CleDroit::AlertesSmsParJour, CleDroit::EmploiOffresMois, CleDroit::ServiceOffresActives,
                CleDroit::ReponsesDemandesMois, CleDroit::PublicationRemonteesMois];

        $lignes = [];

        foreach ($cles as $cle) {
            $limite = $droits->limite($cle);

            // Capacité absente de la formule (SMS, remontées incluses) : rien à afficher.
            if ($limite === 0 && ! $cle->estQuota()) {
                continue;
            }

            $lignes[] = ['cle' => $cle, 'consomme' => $droits->consommation($cle), 'limite' => $limite, 'credits' => $droits->creditsRestants($cle)];
        }

        return $lignes;
    }

    private function ameliore(Plan $plan, CleDroit $cle, int $actuelle): bool
    {
        $limite = $plan->limites->first(fn (PlanLimite $ligne): bool => $ligne->cle === $cle);

        if ($limite === null) {
            return false;
        }

        $valeur = (int) $limite->valeur;

        return $valeur === CleDroit::ILLIMITE || $valeur > $actuelle;
    }
}
