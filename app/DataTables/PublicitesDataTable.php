<?php

namespace App\DataTables;

use App\Enums\EmplacementPublicite;
use App\Models\Publicite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * Publicités (D-074) : aperçu, emplacement, période, état, impressions, clics, taux de clic.
 *
 * @extends TableAdmin<Publicite>
 */
class PublicitesDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        $aujourdhui = Carbon::today()->toDateString();

        return Publicite::query()
            ->select(['id', 'titre', 'annonceur', 'emplacement', 'debut_le', 'fin_le', 'active', 'poids', 'nb_impressions', 'nb_clics', 'cree_le'])
            ->with('media')
            ->when($requete->filled('emplacement'), fn (Builder $r) => $r->where('emplacement', $requete->string('emplacement')->toString()))
            ->when($requete->input('etat') === 'en_cours', fn (Builder $r) => $r->where('active', true)
                ->where(fn (Builder $d) => $d->whereNull('debut_le')->orWhere('debut_le', '<=', $aujourdhui))
                ->where(fn (Builder $f) => $f->whereNull('fin_le')->orWhere('fin_le', '>=', $aujourdhui)))
            ->when($requete->input('etat') === 'programmee', fn (Builder $r) => $r->where('active', true)->where('debut_le', '>', $aujourdhui))
            ->when($requete->input('etat') === 'terminee', fn (Builder $r) => $r->where('fin_le', '<', $aujourdhui))
            ->when($requete->input('etat') === 'inactive', fn (Builder $r) => $r->where('active', false));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'apercu', 'titre' => 'Aperçu', 'tri' => false, 'classe' => 'w-24'],
            ['data' => 'titre', 'titre' => 'Publicité', 'recherche' => true],
            ['data' => 'emplacement', 'titre' => 'Emplacement'],
            ['data' => 'periode', 'name' => 'debut_le', 'titre' => 'Période'],
            ['data' => 'etat', 'titre' => 'État', 'tri' => false],
            ['data' => 'nb_impressions', 'titre' => 'Impressions', 'classe' => 'text-right'],
            ['data' => 'nb_clics', 'titre' => 'Clics', 'classe' => 'text-right'],
            ['data' => 'taux', 'titre' => 'Taux de clic', 'tri' => false, 'classe' => 'text-right'],
            ['data' => 'actions', 'titre' => 'Actions', 'tri' => false, 'classe' => 'text-right w-16'],
        ];
    }

    public function tri(): array
    {
        return [3, 'desc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('apercu', fn (Publicite $p): string => ($url = $p->urlImage(Publicite::IMAGE_PRINCIPALE))
                ? '<img src="'.e($url).'" alt="" loading="lazy" class="max-h-14 w-20 rounded object-contain ring-1 ring-slate-200">'
                : '<span class="text-xs text-red-700">Sans image</span>')
            ->editColumn('titre', fn (Publicite $p): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.publicites.edit', $p)).'">'.e($p->titre).'</a>'
                .'<span class="block text-xs text-slate-500">'.e($p->annonceur).' · poids '.$p->poids.'</span>')
            ->editColumn('emplacement', fn (Publicite $p): string => e($p->emplacement->libelle()).'<span class="block text-xs text-slate-500">'.e($p->emplacement->formatPrincipal()['largeur'].' × '.$p->emplacement->formatPrincipal()['hauteur']).'</span>')
            ->addColumn('periode', fn (Publicite $p): string => match (true) {
                $p->debut_le === null && $p->fin_le === null => 'Sans limite',
                $p->fin_le === null => 'Dès le '.$p->debut_le->format('d/m/Y'),
                $p->debut_le === null => 'Jusqu’au '.$p->fin_le->format('d/m/Y'),
                default => $p->debut_le->format('d/m/Y').' → '.$p->fin_le->format('d/m/Y'),
            })
            ->addColumn('etat', fn (Publicite $p): string => view('admin.publicites._etat', ['etat' => $p->etat()])->render())
            ->editColumn('nb_impressions', fn (Publicite $p): string => number_format($p->nb_impressions, 0, ',', ' '))
            ->editColumn('nb_clics', fn (Publicite $p): string => number_format($p->nb_clics, 0, ',', ' '))
            ->addColumn('taux', fn (Publicite $p): string => $p->tauxDeClic() === null ? '—' : number_format((float) $p->tauxDeClic(), 2, ',', ' ').' %')
            ->addColumn('actions', fn (Publicite $p): string => view('admin.publicites._actions', ['publicite' => $p])->render())
            ->rawColumns(['apercu', 'titre', 'emplacement', 'etat', 'actions']);
    }

    /**
     * Emplacements proposés dans le filtre.
     *
     * @return array<string, string>
     */
    public static function optionsEmplacements(): array
    {
        return EmplacementPublicite::options();
    }
}
