<?php

namespace App\DataTables;

use App\Models\Entreprise;
use App\Services\Referentiels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * F — Entreprises (CONTEXTE §13.3.F).
 *
 * @extends TableAdmin<Entreprise>
 */
class EntreprisesDataTable extends TableAdmin
{
    public function __construct(private readonly Referentiels $referentiels) {}

    protected function requete(Request $requete): Builder
    {
        return Entreprise::query()
            ->select(['id', 'nom', 'secteur', 'commune_id', 'proprietaire_id', 'verifiee', 'cree_le'])
            ->with('proprietaire:id,nom,prenoms')
            ->withCount('publications')
            ->when($requete->string('verifiee')->toString() !== '', fn (Builder $r) => $r->where('verifiee', $requete->boolean('verifiee')));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'id', 'titre' => 'N°'],
            ['data' => 'nom', 'titre' => 'Nom', 'recherche' => true],
            ['data' => 'secteur', 'titre' => 'Secteur', 'recherche' => true],
            ['data' => 'commune', 'titre' => 'Commune', 'name' => 'commune_id'],
            ['data' => 'proprietaire', 'titre' => 'Propriétaire', 'tri' => false],
            ['data' => 'publications_count', 'titre' => 'Publications'],
            ['data' => 'verifiee', 'titre' => 'Vérifiée'],
            ['data' => 'cree_le', 'titre' => 'Créée le'],
        ];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('nom', fn (Entreprise $e): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.entreprises.show', $e)).'">'.e($e->nom).'</a>')
            ->addColumn('commune', fn (Entreprise $e): string => $this->referentiels->nomCommune($e->commune_id) ?? '—')
            ->addColumn('proprietaire', fn (Entreprise $e): string => $e->proprietaire?->nomAffiche() ?? '—')
            ->editColumn('verifiee', fn (Entreprise $e): string => $e->verifiee ? '✔ Oui' : 'Non')
            ->editColumn('cree_le', fn (Entreprise $e): string => (string) $e->cree_le?->format('d/m/Y'))
            ->rawColumns(['nom']);
    }
}
