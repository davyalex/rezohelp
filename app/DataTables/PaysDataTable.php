<?php

namespace App\DataTables;

use App\Models\Pays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * G — Pays ouverts à l'inscription (CONTEXTE §13.3.G) : un pays inactif disparaît du sélecteur.
 *
 * @extends TableAdmin<Pays>
 */
class PaysDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Pays::query()->select(['id', 'code', 'nom', 'indicatif', 'actif', 'ordre']);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'ordre', 'titre' => 'Ordre'],
            ['data' => 'nom', 'titre' => 'Pays', 'recherche' => true],
            ['data' => 'code', 'titre' => 'Code', 'recherche' => true],
            ['data' => 'indicatif', 'titre' => 'Indicatif'],
            ['data' => 'actif', 'titre' => 'Actif'],
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
            ->editColumn('nom', fn (Pays $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.pays.edit', $p)).'">'.e($p->nom).'</a>')
            ->editColumn('actif', fn (Pays $p): string => $p->actif ? 'Oui' : 'Non')
            ->addColumn('deplacer', fn (Pays $p): string => view('admin._deplacer', ['element' => $p, 'route' => 'admin.pays.deplacer'])->render())
            ->rawColumns(['nom', 'deplacer']);
    }
}
