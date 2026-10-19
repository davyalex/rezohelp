<?php

namespace App\DataTables;

use App\Models\ImportVeille;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

/**
 * E — Historique des imports de la veille (CONTEXTE §13.3.E).
 *
 * @extends TableAdmin<ImportVeille>
 */
class ImportsVeilleDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return ImportVeille::query()->select(['id', 'fichier_drive_id', 'nom_fichier', 'statut', 'nb_lignes', 'nb_importees', 'nb_doublons', 'nb_rejetees', 'erreur', 'cree_le']);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'cree_le', 'titre' => 'Date'],
            ['data' => 'nom_fichier', 'titre' => 'Fichier', 'recherche' => true],
            ['data' => 'nb_lignes', 'titre' => 'Lignes'],
            ['data' => 'nb_importees', 'titre' => 'Importées'],
            ['data' => 'nb_doublons', 'titre' => 'Doublons'],
            ['data' => 'nb_rejetees', 'titre' => 'Rejetées'],
            ['data' => 'statut', 'titre' => 'Statut'],
            ['data' => 'erreur', 'titre' => 'Erreur', 'tri' => false],
        ];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('cree_le', fn (ImportVeille $i): string => (string) $i->cree_le?->format('d/m/Y H:i'))
            ->editColumn('nom_fichier', fn (ImportVeille $i): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.veille.imports.show', $i)).'">'.e($i->nom_fichier).'</a>')
            ->editColumn('statut', fn (ImportVeille $i): string => self::statut($i->statut))
            ->editColumn('erreur', fn (ImportVeille $i): string => Str::limit((string) $i->erreur, 80))
            ->rawColumns(['nom_fichier', 'statut']);
    }
}
