<?php

namespace App\DataTables;

use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Models\Publication;
use App\Services\Referentiels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * C — Toutes les publications (CONTEXTE §13.3.C), filtrées par type, statut, source, commune,
 * catégorie, auteur et période ; recherche dans le titre.
 *
 * @extends TableAdmin<Publication>
 */
class PublicationsDataTable extends TableAdmin
{
    public function __construct(private readonly Referentiels $referentiels) {}

    protected function requete(Request $requete): Builder
    {
        $du = $requete->filled('du') ? Carbon::make($requete->string('du')->toString()) : null;
        $au = $requete->filled('au') ? Carbon::make($requete->string('au')->toString()) : null;

        return Publication::query()
            ->select(['id', 'type', 'sous_type', 'statut', 'titre', 'slug', 'auteur_id', 'auteur_nom_affiche', 'source', 'source_nom', 'commune_id', 'publiee_le', 'expire_le', 'cree_le'])
            ->when(TypePublication::tryFrom($requete->string('type')->toString()), fn (Builder $r, TypePublication $type) => $r->where('type', $type->value))
            ->when(StatutPublication::tryFrom($requete->string('statut')->toString()), fn (Builder $r, StatutPublication $statut) => $r->where('statut', $statut->value))
            ->when(SourcePublication::tryFrom($requete->string('source')->toString()), fn (Builder $r, SourcePublication $source) => $r->where('source', $source->value))
            ->when($requete->integer('commune_id') > 0, fn (Builder $r) => $r->where('commune_id', $requete->integer('commune_id')))
            ->when($requete->integer('categorie_id') > 0, fn (Builder $r) => $r->whereIn('categorie_id', $this->referentiels->idsAvecSousCategories($requete->integer('categorie_id'))))
            ->when($requete->integer('auteur_id') > 0, fn (Builder $r) => $r->where('auteur_id', $requete->integer('auteur_id')))
            ->when($du, fn (Builder $r, Carbon $date) => $r->where('cree_le', '>=', $date->startOfDay()))
            ->when($au, fn (Builder $r, Carbon $date) => $r->where('cree_le', '<=', $date->endOfDay()));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'id', 'titre' => 'N°'],
            ['data' => 'type', 'titre' => 'Type'],
            ['data' => 'titre', 'titre' => 'Titre', 'recherche' => true],
            ['data' => 'auteur', 'titre' => 'Auteur / source', 'name' => 'auteur_nom_affiche', 'recherche' => true],
            ['data' => 'commune', 'titre' => 'Commune', 'name' => 'commune_id'],
            ['data' => 'statut', 'titre' => 'Statut'],
            ['data' => 'publiee_le', 'titre' => 'Publiée le'],
            ['data' => 'expire_le', 'titre' => 'Expire le'],
            ['data' => 'actions', 'titre' => 'Actions', 'tri' => false],
        ];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('type', fn (Publication $p): string => $p->type->libelle())
            ->editColumn('titre', fn (Publication $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.publications.edit', $p)).'">'.e($p->titre).'</a>')
            ->addColumn('auteur', fn (Publication $p): string => $p->source === SourcePublication::VeilleIa ? 'via '.($p->source_nom ?? 'veille') : (string) $p->auteur_nom_affiche)
            ->addColumn('commune', fn (Publication $p): string => $this->referentiels->nomCommune($p->commune_id) ?? '—')
            ->editColumn('statut', fn (Publication $p): string => self::statut($p->statut))
            ->editColumn('publiee_le', fn (Publication $p): string => (string) $p->publiee_le?->format('d/m/Y'))
            ->editColumn('expire_le', fn (Publication $p): string => (string) $p->expire_le?->format('d/m/Y'))
            ->addColumn('actions', fn (Publication $p): string => view('admin.publications._actions', ['publication' => $p])->render())
            ->rawColumns(['titre', 'statut', 'actions']);
    }
}
