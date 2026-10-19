<?php

namespace App\DataTables;

use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Models\Publication;
use App\Services\Referentiels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * File de modération (CONTEXTE §13.3.B) : publications en attente, les plus risquées d'abord.
 *
 * @extends TableAdmin<Publication>
 */
class ModerationDataTable extends TableAdmin
{
    public function __construct(private readonly Referentiels $referentiels) {}

    protected function requete(Request $requete): Builder
    {
        return self::fileDAttente()
            ->select(['id', 'type', 'sous_type', 'titre', 'auteur_nom_affiche', 'source', 'source_nom', 'commune_id', 'score_fiabilite', 'cree_le'])
            ->when(TypePublication::tryFrom($requete->string('type')->toString()), fn (Builder $r, TypePublication $type) => $r->where('type', $type->value))
            ->when(SourcePublication::tryFrom($requete->string('source')->toString()), fn (Builder $r, SourcePublication $source) => $r->where('source', $source->value));
    }

    /**
     * Ordre de la file, partagé avec le passage à la publication suivante.
     *
     * @return Builder<Publication>
     */
    public static function fileDAttente(): Builder
    {
        return Publication::query()->where('statut', StatutPublication::EnAttente->value);
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'selection', 'titre' => 'Sélection', 'tri' => false],
            ['data' => 'type', 'titre' => 'Type'],
            ['data' => 'titre', 'titre' => 'Titre', 'recherche' => true],
            ['data' => 'auteur', 'titre' => 'Auteur / source', 'name' => 'auteur_nom_affiche', 'recherche' => true],
            ['data' => 'commune', 'titre' => 'Commune', 'name' => 'commune_id'],
            ['data' => 'score_fiabilite', 'titre' => 'Score'],
            ['data' => 'cree_le', 'titre' => 'Reçue'],
        ];
    }

    public function tri(): array
    {
        return [5, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('selection', fn (Publication $p): string => self::caseSelection($p))
            ->editColumn('type', fn (Publication $p): string => $p->type->libelle().($p->sous_type !== null ? ' · '.$p->sous_type->libelle() : ''))
            ->editColumn('titre', fn (Publication $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.moderation.show', $p)).'">'.e($p->titre).'</a>')
            ->addColumn('auteur', fn (Publication $p): string => $p->source === SourcePublication::VeilleIa ? 'via '.($p->source_nom ?? 'veille') : (string) $p->auteur_nom_affiche)
            ->addColumn('commune', fn (Publication $p): string => $this->referentiels->nomCommune($p->commune_id) ?? '—')
            ->editColumn('score_fiabilite', fn (Publication $p): string => view('admin.moderation._score', ['score' => $p->score_fiabilite])->render())
            ->editColumn('cree_le', fn (Publication $p): string => (string) $p->cree_le?->format('d/m/Y H:i'))
            ->rawColumns(['selection', 'titre', 'score_fiabilite']);
    }
}
