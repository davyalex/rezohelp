<?php

namespace App\DataTables;

use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * K — Abonnements (CONTEXTE §14.10), filtrables par plan, statut et échéance.
 *
 * @extends TableAdmin<Abonnement>
 */
class AbonnementsDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Abonnement::query()
            ->select(['id', 'titulaire_type', 'titulaire_id', 'plan_id', 'statut', 'origine', 'debut_le', 'fin_le', 'motif'])
            ->with(['plan:id,nom', 'titulaire'])
            ->when($requete->integer('plan_id') > 0, fn (Builder $r) => $r->where('plan_id', $requete->integer('plan_id')))
            ->when(StatutAbonnement::tryFrom($requete->string('statut')->toString()), fn (Builder $r, StatutAbonnement $s) => $r->where('statut', $s->value))
            ->when($requete->integer('echeance') > 0, fn (Builder $r) => $r->whereBetween('fin_le', [now(), now()->addDays($requete->integer('echeance'))]));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'titulaire', 'titre' => 'Titulaire', 'tri' => false],
            ['data' => 'plan', 'titre' => 'Plan', 'name' => 'plan_id'],
            ['data' => 'statut', 'titre' => 'Statut'],
            ['data' => 'origine', 'titre' => 'Origine'],
            ['data' => 'debut_le', 'titre' => 'Début'],
            ['data' => 'fin_le', 'titre' => 'Fin'],
            ['data' => 'actions', 'titre' => 'Actions', 'tri' => false],
        ];
    }

    public function tri(): array
    {
        return [5, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('titulaire', function (Abonnement $a): string {
                $titulaire = $a->titulaire;

                return match (true) {
                    $titulaire instanceof Entreprise => '<a class="text-brand hover:underline" href="'.e(route('admin.entreprises.show', $titulaire)).'">'.e($titulaire->nom).'</a> <span class="text-xs text-slate-500">entreprise</span>',
                    $titulaire instanceof Utilisateur => '<a class="text-brand hover:underline" href="'.e(route('admin.utilisateurs.show', $titulaire)).'">'.e($titulaire->nomAffiche()).'</a>',
                    default => '—',
                };
            })
            ->addColumn('plan', fn (Abonnement $a): string => $a->plan->nom)
            ->editColumn('statut', fn (Abonnement $a): string => self::statut($a->statut))
            ->editColumn('origine', fn (Abonnement $a): string => $a->origine->libelle().($a->motif ? ' — '.$a->motif : ''))
            ->editColumn('debut_le', fn (Abonnement $a): string => (string) $a->debut_le?->format('d/m/Y'))
            ->editColumn('fin_le', fn (Abonnement $a): string => (string) $a->fin_le?->format('d/m/Y'))
            ->addColumn('actions', fn (Abonnement $a): string => view('admin.abonnements._actions', ['abonnement' => $a])->render())
            ->rawColumns(['titulaire', 'statut', 'actions']);
    }
}
