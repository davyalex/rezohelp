<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\Entreprise;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\Offres;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mon espace → Mon abonnement » (CONTEXTE §14.11) : pour le membre et chacune de ses
 * entreprises, formules en cours, échéances, compteurs restants, renouvellement et essais ;
 * puis l'historique des commandes et des reçus.
 */
class AbonnementController extends Controller
{
    public const int COMMANDES_AFFICHEES = 20;

    public function index(Request $request, Offres $offres): View
    {
        $membre = $request->user();
        assert($membre instanceof Utilisateur);

        $titulaires = collect([$membre])->concat(
            Entreprise::query()->where('proprietaire_id', $membre->id)->orderBy('nom')->get(['id', 'nom', 'proprietaire_id', 'verifiee']),
        );

        $situations = $titulaires->map(fn (Utilisateur|Entreprise $titulaire): array => [
            'titulaire' => $titulaire,
            'nom' => $titulaire instanceof Entreprise ? $titulaire->nom : 'Mon compte personnel',
            'abonnements' => Abonnement::query()
                ->where('titulaire_type', $titulaire->getMorphClass())
                ->where('titulaire_id', $titulaire->getKey())
                ->whereIn('statut', array_map(fn ($statut): string => $statut->value, Droits::STATUTS_EN_COURS))
                ->with('plan:id,code,nom,prix_fcfa,duree_jours')
                ->orderBy('fin_le')
                ->get(),
            'compteurs' => $offres->compteurs($titulaire),
            'plans' => $offres->plansAchetables($titulaire)
                ->map(fn ($plan): array => ['plan' => $plan, 'essai' => $offres->essaiDisponible($membre, $plan)]),
        ]);

        return view('espace.abonnement', [
            'membre' => $membre,
            'situations' => $situations,
            'commandes' => Commande::query()
                ->where('utilisateur_id', $membre->id)
                ->with(['plan:id,nom', 'produit:id,nom'])
                ->latest('cree_le')->latest('id')
                ->limit(self::COMMANDES_AFFICHEES)
                ->get(),
        ]);
    }
}
