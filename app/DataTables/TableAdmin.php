<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables;

/**
 * Liste du back-office (CONTEXTE §13.1) : toujours traitée côté serveur par Yajra DataTables.
 * La vue reçoit la configuration des colonnes (x-admin.table) ; admin.js crée la liste.
 *
 * @template TModele of Model
 */
abstract class TableAdmin
{
    /**
     * Requête de base, filtres personnalisés déjà appliqués.
     *
     * @return Builder<TModele>
     */
    abstract protected function requete(Request $requete): Builder;

    /**
     * Colonnes affichées : data (clé JSON), titre, name (colonne SQL pour le tri et la recherche),
     * tri, recherche, classe.
     *
     * @return list<array{data: string, titre: string, name?: string, tri?: bool, recherche?: bool, classe?: string}>
     */
    abstract public function colonnes(): array;

    /**
     * Mise en forme des colonnes calculées (badges, liens, actions en vue partielle).
     */
    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table;
    }

    /**
     * Tri initial : [rang de la colonne, sens].
     *
     * @return array{0: int, 1: 'asc'|'desc'}
     */
    public function tri(): array
    {
        return [0, 'desc'];
    }

    public function reponse(Request $requete): JsonResponse
    {
        return $this->formater(DataTables::eloquent($this->requete($requete)))->toJson();
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(string $url): array
    {
        return [
            'ajax' => $url,
            'order' => [$this->tri()],
            'columns' => array_map(fn (array $colonne): array => [
                'data' => $colonne['data'],
                'name' => $colonne['name'] ?? $colonne['data'],
                'orderable' => $colonne['tri'] ?? true,
                'searchable' => $colonne['recherche'] ?? false,
                'className' => $colonne['classe'] ?? '',
            ], $this->colonnes()),
        ];
    }

    /**
     * Badge de statut (x-admin.badge-statut) d'un enum à libellé.
     */
    protected static function statut(\BackedEnum $statut): string
    {
        return view('admin._statut', ['statut' => $statut])->render();
    }

    /**
     * Case à cocher des actions groupées.
     */
    protected static function caseSelection(Model $modele): string
    {
        return '<input type="checkbox" data-selection value="'.e((string) $modele->getKey()).'" class="size-5" aria-label="Sélectionner">';
    }
}
