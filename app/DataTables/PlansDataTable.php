<?php

namespace App\DataTables;

use App\Models\Plan;
use App\Services\Droits;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * K — Plans (CONTEXTE §14.10).
 *
 * @extends TableAdmin<Plan>
 */
class PlansDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Plan::query()
            ->select(['id', 'code', 'nom', 'cible', 'famille', 'prix_fcfa', 'duree_jours', 'duree_essai_jours', 'est_defaut', 'actif', 'visible', 'ordre'])
            ->withCount(['abonnements as abonnements_en_cours' => fn ($r) => $r->whereIn('statut', array_map(fn ($s) => $s->value, Droits::STATUTS_EN_COURS))]);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'cible', 'titre' => 'Cible'],
            ['data' => 'nom', 'titre' => 'Plan', 'recherche' => true],
            ['data' => 'code', 'titre' => 'Code', 'recherche' => true],
            ['data' => 'prix_fcfa', 'titre' => 'Prix'],
            ['data' => 'duree_essai_jours', 'titre' => 'Essai'],
            ['data' => 'etat', 'titre' => 'État', 'tri' => false],
            ['data' => 'abonnements_en_cours', 'titre' => 'Abonnés'],
        ];
    }

    public function tri(): array
    {
        return [0, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('cible', fn (Plan $p): string => $p->cible->libelle())
            ->editColumn('nom', fn (Plan $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.plans.edit', $p)).'">'.e($p->nom).'</a>')
            ->editColumn('prix_fcfa', fn (Plan $p): string => $p->prix_fcfa === 0 ? 'Gratuit' : Montant::fcfa($p->prix_fcfa).' / '.$p->duree_jours.' j')
            ->editColumn('duree_essai_jours', fn (Plan $p): string => $p->duree_essai_jours > 0 ? $p->duree_essai_jours.' j ('.$p->famille.')' : '—')
            ->addColumn('etat', fn (Plan $p): string => implode(' · ', array_filter([
                $p->est_defaut ? 'par défaut' : null,
                $p->actif ? 'actif' : 'inactif',
                $p->visible ? 'visible' : 'masqué',
            ])))
            ->rawColumns(['nom']);
    }
}
