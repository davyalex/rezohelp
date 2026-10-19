<?php

namespace App\DataTables;

use App\Enums\MotifSignalement;
use App\Models\Publication;
use App\Models\Signalement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * D — Signalements ouverts, groupés par publication (CONTEXTE §13.3.D) : nombre, motifs, premier signalement.
 *
 * @extends TableAdmin<Publication>
 */
class SignalementsDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        $ouverts = fn ($r) => $r->whereNull('traite_le');

        return Publication::withTrashed()
            ->whereHas('signalements', $ouverts)
            ->select(['id', 'titre', 'statut', 'auteur_nom_affiche', 'supprime_le'])
            ->withCount(['signalements as nb_signalements' => $ouverts])
            ->addSelect([
                'premier_signalement' => Signalement::query()->selectRaw('min(cree_le)')
                    ->whereColumn('publication_id', 'publications.id')->whereNull('traite_le'),
                'motifs' => Signalement::query()->selectRaw('group_concat(distinct motif)')
                    ->whereColumn('publication_id', 'publications.id')->whereNull('traite_le'),
            ]);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'titre', 'titre' => 'Publication', 'recherche' => true],
            ['data' => 'auteur_nom_affiche', 'titre' => 'Auteur', 'recherche' => true],
            ['data' => 'statut', 'titre' => 'Statut'],
            ['data' => 'nb_signalements', 'titre' => 'Signalements'],
            ['data' => 'motifs', 'titre' => 'Motifs', 'tri' => false],
            ['data' => 'premier_signalement', 'titre' => 'Premier'],
        ];
    }

    public function tri(): array
    {
        return [3, 'desc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('titre', fn (Publication $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.signalements.show', $p->id)).'">'.e($p->titre).'</a>')
            ->editColumn('statut', fn (Publication $p): string => self::statut($p->statut))
            ->editColumn('motifs', fn (Publication $p): string => collect(explode(',', (string) $p->getAttribute('motifs')))
                ->map(fn (string $motif): string => MotifSignalement::tryFrom($motif)?->libelle() ?? $motif)
                ->implode(', '))
            ->editColumn('premier_signalement', fn (Publication $p): string => (string) Carbon::make($p->getAttribute('premier_signalement'))?->format('d/m/Y H:i'))
            ->rawColumns(['titre', 'statut']);
    }
}
