<?php

namespace App\DataTables;

use App\Models\Produit;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * K — Produits ponctuels (CONTEXTE §14.10).
 *
 * @extends TableAdmin<Produit>
 */
class ProduitsDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Produit::query()->select(['id', 'code', 'nom', 'cible', 'type', 'prix_fcfa', 'duree_jours', 'actif', 'ordre']);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'ordre', 'titre' => 'Ordre'],
            ['data' => 'nom', 'titre' => 'Produit', 'recherche' => true],
            ['data' => 'code', 'titre' => 'Code', 'recherche' => true],
            ['data' => 'type', 'titre' => 'Type'],
            ['data' => 'prix_fcfa', 'titre' => 'Prix'],
            ['data' => 'actif', 'titre' => 'Actif'],
        ];
    }

    public function tri(): array
    {
        return [0, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('nom', fn (Produit $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.produits.edit', $p)).'">'.e($p->nom).'</a>')
            ->editColumn('type', fn (Produit $p): string => $p->type->libelle())
            ->editColumn('prix_fcfa', fn (Produit $p): string => Montant::fcfa($p->prix_fcfa).($p->duree_jours !== null ? ' / '.$p->duree_jours.' j' : ''))
            ->editColumn('actif', fn (Produit $p): string => $p->actif ? 'Oui' : 'Non')
            ->rawColumns(['nom']);
    }
}
