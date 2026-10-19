<?php

namespace App\Actions\Commande;

use App\Enums\CleDroit;
use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Enums\StatutCommande;
use App\Enums\StatutPublication;
use App\Enums\TypeArticle;
use App\Enums\TypeMiseEnAvant;
use App\Enums\TypeProduit;
use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\Credit;
use App\Models\MiseEnAvant;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Notifications\CommandePayee;
use App\Services\NumeroSequentiel;
use Illuminate\Support\Facades\DB;

/**
 * Active une commande payée (CONTEXTE §14.7) : numéro de reçu séquentiel, puis
 * - plan : abonnement créé, ou ajouté à la suite de la période en cours ; un essai de la
 *   même famille est remplacé immédiatement ;
 * - produit : crédit d'offre, remontée ou mise en avant.
 * Appelée uniquement par TraiterNotificationPaiement (statut revérifié, sous verrou).
 */
class ActiverCommande
{
    public function __construct(
        private readonly NumeroSequentiel $numeros,
        private readonly RemonterPublication $remonter,
    ) {}

    /**
     * @param  array<string, mixed>  $donneesPasserelle
     */
    public function executer(Commande $commande, ?string $moyenPaiement, array $donneesPasserelle = []): void
    {
        DB::transaction(function () use ($commande, $moyenPaiement, $donneesPasserelle): void {
            $commande->forceFill([
                'statut' => StatutCommande::Payee,
                'payee_le' => now(),
                'moyen_paiement' => $moyenPaiement,
                'numero_recu' => $this->numeros->suivant(NumeroSequentiel::RECU),
                'donnees_passerelle' => $donneesPasserelle,
            ])->save();

            if ($commande->article_type === TypeArticle::Plan) {
                $this->abonner($commande, Plan::query()->findOrFail($commande->article_id));
            } else {
                $this->appliquerProduit($commande, Produit::query()->findOrFail($commande->article_id));
            }
        });

        $commande->loadMissing('utilisateur');
        $commande->utilisateur?->notify(new CommandePayee($commande));
    }

    private function abonner(Commande $commande, Plan $plan): void
    {
        $enCours = Abonnement::query()
            ->where('titulaire_type', $commande->titulaire_type)
            ->where('titulaire_id', $commande->titulaire_id)
            ->whereIn('statut', [StatutAbonnement::Essai->value, StatutAbonnement::Actif->value, StatutAbonnement::Grace->value])
            ->whereHas('plan', fn ($requete) => $plan->famille !== null
                ? $requete->where('famille', $plan->famille)
                : $requete->whereKey($plan->id))
            ->lockForUpdate()
            ->get();

        $debut = now();

        foreach ($enCours as $abonnement) {
            if ($abonnement->statut === StatutAbonnement::Actif && $abonnement->fin_le !== null && $abonnement->fin_le->gt($debut)) {
                // Renouvellement : la nouvelle période commence à la fin de la période payée en cours.
                $debut = $abonnement->fin_le->copy();
            } else {
                // Essai remplacé par la formule payée, ou délai de grâce soldé.
                $abonnement->forceFill(['statut' => StatutAbonnement::Expire, 'fin_le' => min(now(), $abonnement->fin_le ?? now())])->save();
            }
        }

        Abonnement::query()->create([
            'titulaire_type' => $commande->titulaire_type,
            'titulaire_id' => $commande->titulaire_id,
            'plan_id' => $plan->id,
            'statut' => StatutAbonnement::Actif,
            'origine' => OrigineAbonnement::Paiement,
            'debut_le' => $debut,
            'fin_le' => $debut->copy()->addDays($plan->duree_jours),
            'commande_id' => $commande->id,
        ]);
    }

    private function appliquerProduit(Commande $commande, Produit $produit): void
    {
        if ($produit->type === TypeProduit::OffreSupplementaire) {
            Credit::query()->create([
                'titulaire_type' => $commande->titulaire_type,
                'titulaire_id' => $commande->titulaire_id,
                'cle' => CleDroit::EmploiOffresMois,
                'quantite' => 1,
                'expire_le' => $produit->duree_jours !== null ? now()->addDays($produit->duree_jours) : null,
                'commande_id' => $commande->id,
            ]);

            return;
        }

        $publication = Publication::query()->findOrFail($commande->publication_id);

        if ($produit->type === TypeProduit::Remontee) {
            $this->remonter->payee($publication, $commande);

            return;
        }

        $miseEnAvant = new MiseEnAvant([
            'publication_id' => $publication->id,
            'commande_id' => $commande->id,
            'type' => $produit->type === TypeProduit::UneCommune ? TypeMiseEnAvant::UneCommune : TypeMiseEnAvant::SponsoriseFil,
            'commune_id' => $produit->type === TypeProduit::UneCommune ? ($commande->commune_id ?? $publication->commune_id) : null,
            'debut_le' => now(),
            'fin_le' => now()->addDays($produit->duree_jours ?? 0),
        ]);

        // La publication a pu sortir du fil entre l'achat et le paiement : la durée est conservée.
        if ($publication->statut !== StatutPublication::Publiee) {
            $miseEnAvant->suspendue_le = now();
        }

        $miseEnAvant->save();
    }
}
