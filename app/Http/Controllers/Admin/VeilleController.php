<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ImportsVeilleDataTable;
use App\Models\AppelIa;
use App\Models\ImportVeille;
use App\Models\Publication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * E — Veille IA : historique des imports, détail, coût de l'API IA par mois (CONTEXTE §13.3.E).
 */
class VeilleController extends ControleurAdmin
{
    public const int MOIS_AFFICHES = 12;

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
            'publications' => Publication::withTrashed()
                ->where('import_veille_id', $import->id)
                ->latest('id')
                ->get(['id', 'titre', 'statut', 'score_fiabilite', 'source_nom', 'supprime_le']),
        ]);
    }
}
