<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererImportVeille;
use App\DataTables\ImportsVeilleDataTable;
use App\Models\AppelIa;
use App\Models\ImportVeille;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * E — Veille IA : historique des imports, détail ligne par ligne, relance, import à la demande
 * et coût de l'API IA par mois (CONTEXTE §13.3.E).
 */
class VeilleController extends ControleurAdmin
{
    public const int MOIS_AFFICHES = 12;

    public function __construct(private readonly GererImportVeille $gerer) {}

    public function index(Request $requete, ImportsVeilleDataTable $table): View|JsonResponse
    {
        if ($requete->ajax()) {
            return $table->reponse($requete);
        }

        return view('admin.veille.index', [
            'table' => $table,
            'couts' => AppelIa::query()
                ->where('cree_le', '>=', now()->startOfMonth()->subMonths(self::MOIS_AFFICHES - 1))
                ->selectRaw("date_format(cree_le, '%Y-%m') as mois, count(*) as appels, sum(tokens_entree) as entree, sum(tokens_sortie) as sortie, sum(cout_estime_fcfa) as cout")
                ->groupBy('mois')
                ->orderByDesc('mois')
                ->toBase()
                ->get(),
        ]);
    }

    public function show(ImportVeille $import): View
    {
        return view('admin.veille.show', [
            'import' => $import,
            'lignes' => $import->lignes()->with('publication:id,titre,statut,score_fiabilite,supprime_le')->orderBy('numero')->get(),
        ]);
    }

    public function importerMaintenant(Request $requete): RedirectResponse
    {
        $this->gerer->importerMaintenant($this->admin($requete));

        return back()->with('statut', 'Recherche de nouveaux fichiers lancée : les imports apparaîtront dans quelques instants.');
    }

    public function relancer(Request $requete, ImportVeille $import): RedirectResponse
    {
        $this->gerer->relancer($import, $this->admin($requete));

        return back()->with('statut', 'Import relancé : les annonces déjà importées ne seront pas dupliquées.');
    }
}
