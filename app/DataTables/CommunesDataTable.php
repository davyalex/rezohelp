<?php

namespace App\DataTables;

use App\Models\Commune;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * G — Communes (CONTEXTE §13.3.G).
 *
 * @extends TableAdmin<Commune>
 */
class CommunesDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Commune::query()->select(['id', 'pays_code', 'ville', 'nom', 'slug', 'actif', 'ordre']);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'ordre', 'titre' => 'Ordre'],
            ['data' => 'nom', 'titre' => 'Commune', 'recherche' => true],
            ['data' => 'ville', 'titre' => 'Ville', 'recherche' => true],
            ['data' => 'pays_code', 'titre' => 'Pays'],
            ['data' => 'actif', 'titre' => 'Active'],
            ['data' => 'deplacer', 'titre' => 'Déplacer', 'tri' => false],
        ];
    }

    public function tri(): array
    {
        return [0, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('nom', fn (Commune $c): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.communes.edit', $c)).'">'.e($c->nom).'</a>')
            ->editColumn('actif', fn (Commune $c): string => $c->actif ? 'Oui' : 'Non')
            ->addColumn('deplacer', fn (Commune $c): string => view('admin._deplacer', ['element' => $c, 'route' => 'admin.communes.deplacer'])->render())
            ->rawColumns(['nom', 'deplacer']);
    }
}
