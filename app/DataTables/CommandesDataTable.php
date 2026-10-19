<?php

namespace App\DataTables;

use App\Enums\PasserelleCommande;
use App\Enums\StatutCommande;
use App\Models\Commande;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * K — Commandes et paiements (CONTEXTE §14.10), filtrables par statut, passerelle et période.
 * La même requête sert à l'export CSV.
 *
 * @extends TableAdmin<Commande>
 */
class CommandesDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return self::filtrees($requete)
            ->select(['id', 'numero', 'utilisateur_id', 'article_type', 'article_id', 'montant_ttc_fcfa', 'statut', 'passerelle', 'moyen_paiement', 'numero_recu', 'payee_le', 'cree_le'])
            ->with(['utilisateur:id,nom,prenoms', 'plan:id,nom', 'produit:id,nom']);
    }

    /**
     * @return Builder<Commande>
     */
    public static function filtrees(Request $requete): Builder
    {
        $du = $requete->filled('du') ? Carbon::make($requete->string('du')->toString()) : null;
        $au = $requete->filled('au') ? Carbon::make($requete->string('au')->toString()) : null;

        return Commande::query()
            ->when(StatutCommande::tryFrom($requete->string('statut')->toString()), fn (Builder $r, StatutCommande $s) => $r->where('statut', $s->value))
            ->when(PasserelleCommande::tryFrom($requete->string('passerelle')->toString()), fn (Builder $r, PasserelleCommande $p) => $r->where('passerelle', $p->value))
            ->when($du, fn (Builder $r, Carbon $date) => $r->where('cree_le', '>=', $date->startOfDay()))
            ->when($au, fn (Builder $r, Carbon $date) => $r->where('cree_le', '<=', $date->endOfDay()));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'cree_le', 'titre' => 'Date'],
            ['data' => 'numero', 'titre' => 'Commande', 'recherche' => true],
            ['data' => 'acheteur', 'titre' => 'Acheteur', 'tri' => false],
            ['data' => 'article', 'titre' => 'Article', 'tri' => false],
            ['data' => 'montant_ttc_fcfa', 'titre' => 'Montant'],
            ['data' => 'statut', 'titre' => 'Statut'],
            ['data' => 'moyen_paiement', 'titre' => 'Moyen'],
            ['data' => 'numero_recu', 'titre' => 'Reçu', 'recherche' => true],
        ];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('cree_le', fn (Commande $c): string => (string) $c->cree_le?->format('d/m/Y H:i'))
            ->editColumn('numero', fn (Commande $c): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.commandes.show', $c)).'">'.e($c->numero).'</a>')
            ->addColumn('acheteur', fn (Commande $c): string => $c->utilisateur?->nomAffiche() ?? '—')
            ->addColumn('article', fn (Commande $c): string => $c->article()->nom ?? '—')
            ->editColumn('montant_ttc_fcfa', fn (Commande $c): string => Montant::fcfa($c->montant_ttc_fcfa))
            ->editColumn('statut', fn (Commande $c): string => self::statut($c->statut))
            ->editColumn('moyen_paiement', fn (Commande $c): string => trim(($c->moyen_paiement ?? '').' · '.$c->passerelle->libelle(), ' ·'))
            ->rawColumns(['numero', 'statut']);
    }
}
