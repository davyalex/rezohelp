<?php

namespace App\DataTables;

use App\Enums\StatutPublication;
use App\Models\SourceVeille;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * E — Sources de la veille avec leur production et leur taux de rejet sur 30 jours (CONTEXTE §13.3.E).
 *
 * @extends TableAdmin<SourceVeille>
 */
class SourcesVeilleDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        $trenteJours = fn ($r) => $r->where('cree_le', '>=', now()->subDays(30));

        return SourceVeille::query()
            ->select(['id', 'nom', 'domaine', 'niveau_confiance', 'publication_auto', 'actif'])
            ->withCount([
                'publications',
                'publications as publications_30j' => $trenteJours,
                'publications as rejetees_30j' => fn ($r) => $trenteJours($r)->where('statut', StatutPublication::Rejetee->value),
            ]);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'nom', 'titre' => 'Nom', 'recherche' => true],
            ['data' => 'domaine', 'titre' => 'Domaine', 'recherche' => true],
            ['data' => 'niveau_confiance', 'titre' => 'Confiance'],
            ['data' => 'publication_auto', 'titre' => 'Publication auto'],
            ['data' => 'actif', 'titre' => 'Active'],
            ['data' => 'publications_count', 'titre' => 'Importées'],
            ['data' => 'taux_rejet', 'titre' => 'Rejet sur 30 j', 'tri' => false],
        ];
    }

    public function tri(): array
    {
        return [0, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('nom', fn (SourceVeille $s): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.veille.sources.edit', $s)).'">'.e($s->nom).'</a>')
            ->editColumn('domaine', fn (SourceVeille $s): string => $s->domaine ?? '(lignes non rattachées)')
            ->editColumn('niveau_confiance', fn (SourceVeille $s): string => self::statut($s->niveau_confiance))
            ->editColumn('publication_auto', fn (SourceVeille $s): string => $s->publication_auto ? 'Oui' : 'Non')
            ->editColumn('actif', fn (SourceVeille $s): string => $s->actif ? 'Oui' : 'Non')
            ->addColumn('taux_rejet', function (SourceVeille $s): string {
                $total = (int) $s->getAttribute('publications_30j');

                return $total === 0 ? '—' : round((int) $s->getAttribute('rejetees_30j') * 100 / $total).' % ('.$total.')';
            })
            ->rawColumns(['nom', 'niveau_confiance']);
    }
}
