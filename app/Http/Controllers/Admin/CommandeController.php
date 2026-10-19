<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererCommande;
use App\DataTables\CommandesDataTable;
use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\NotificationPaiement;
use App\Services\StatistiquesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * K — Commandes, paiements et revenus (CONTEXTE §14.10).
 */
class CommandeController extends ControleurAdmin
{
    public function __construct(private readonly GererCommande $gerer) {}

    public function index(Request $requete, CommandesDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.commandes.index', ['table' => $table]);
    }

    public function show(Commande $commande): View
    {
        $commande->load(['utilisateur:id,nom,prenoms', 'plan:id,nom', 'produit:id,nom', 'publication:id,titre,slug', 'titulaire', 'abonnement.plan:id,nom']);

        return view('admin.commandes.show', [
            'commande' => $commande,
            'notifications' => NotificationPaiement::query()
                ->whereIn('reference', array_filter([$commande->reference_passerelle, $commande->numero]))
                ->latest('cree_le')
                ->get(),
        ]);
    }

    public function reverifier(Request $requete, Commande $commande): RedirectResponse
    {
        $resultat = $this->gerer->reverifier($commande, $this->admin($requete));

        return back()->with('statut', 'Statut revérifié auprès de la passerelle : '.$resultat->statut->libelle().'.');
    }

    public function rembourser(Request $requete, Commande $commande): RedirectResponse
    {
        $motif = $requete->validate(['motif' => ['required', 'string', 'max:255']])['motif'];

        return $this->appliquer(fn () => $this->gerer->rembourser($commande, $this->admin($requete), $motif), 'Commande marquée remboursée : les droits associés sont arrêtés.');
    }

    /**
     * Export CSV (séparateur « ; », UTF-8 avec BOM pour Excel) avec les filtres de la liste.
     */
    public function export(Request $requete): StreamedResponse
    {
        $nom = 'commandes-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($requete): void {
            $sortie = fopen('php://output', 'w');

            if ($sortie === false) {
                return;
            }

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Commande', 'Date', 'Acheteur', 'Article', 'Montant TTC', 'TVA %', 'Montant HT', 'Statut', 'Passerelle', 'Moyen', 'Payée le', 'Reçu'], ';');

            CommandesDataTable::filtrees($requete)
                ->with(['utilisateur:id,nom,prenoms', 'plan:id,nom', 'produit:id,nom'])
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (Commande $commande) use ($sortie): void {
                    fputcsv($sortie, [
                        $commande->numero,
                        $commande->cree_le?->format('Y-m-d H:i'),
                        $commande->utilisateur?->nomAffiche(),
                        $commande->article()?->nom,
                        $commande->montant_ttc_fcfa,
                        $commande->taux_tva,
                        $commande->montant_ht_fcfa,
                        $commande->statut->libelle(),
                        $commande->passerelle->libelle(),
                        $commande->moyen_paiement,
                        $commande->payee_le?->format('Y-m-d H:i'),
                        $commande->numero_recu,
                    ], ';');
                });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function revenus(StatistiquesService $statistiques): View
    {
        return view('admin.revenus', [
            'revenus' => $statistiques->revenus(),
            'echeances' => Abonnement::query()
                ->whereIn('statut', [StatutAbonnement::Essai->value, StatutAbonnement::Actif->value])
                ->whereBetween('fin_le', [now(), now()->addDays(7)])
                ->with(['plan:id,nom', 'titulaire'])
                ->orderBy('fin_le')
                ->limit(50)
                ->get(),
        ]);
    }
}
