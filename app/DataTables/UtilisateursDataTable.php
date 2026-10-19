<?php

namespace App\DataTables;

use App\Enums\ConfianceUtilisateur;
use App\Http\Controllers\Admin\RechercheController;
use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * F — Comptes (CONTEXTE §13.3.F) : la recherche accepte un numéro sous toutes ses formes
 * (normalisé en E.164) ou un nom ; filtres par rôle, confiance et suspension.
 *
 * @extends TableAdmin<Utilisateur>
 */
class UtilisateursDataTable extends TableAdmin
{
    public function __construct(private readonly TelephoneService $telephones) {}

    protected function requete(Request $requete): Builder
    {
        return Utilisateur::query()
            ->select(['id', 'nom', 'prenoms', 'telephone', 'pays_code', 'confiance', 'suspendu_le', 'suspendu_jusqu_au', 'cree_le', 'derniere_connexion_le'])
            ->with('roles:id,name')
            ->when($requete->filled('role'), fn (Builder $r) => $r->role($requete->string('role')->toString()))
            ->when(ConfianceUtilisateur::tryFrom($requete->string('confiance')->toString()), fn (Builder $r, ConfianceUtilisateur $c) => $r->where('confiance', $c->value))
            ->when($requete->string('etat')->toString() === 'suspendu', fn (Builder $r) => $r->whereNotNull('suspendu_le')
                ->where(fn ($q) => $q->whereNull('suspendu_jusqu_au')->orWhere('suspendu_jusqu_au', '>', now())));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'id', 'titre' => 'N°'],
            ['data' => 'nom', 'titre' => 'Nom'],
            ['data' => 'telephone', 'titre' => 'Téléphone', 'tri' => false],
            ['data' => 'roles', 'titre' => 'Rôles', 'tri' => false],
            ['data' => 'confiance', 'titre' => 'Confiance'],
            ['data' => 'cree_le', 'titre' => 'Inscrit le'],
            ['data' => 'derniere_connexion_le', 'titre' => 'Dernière connexion'],
        ];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            // Recherche par numéro normalisé ou par nom (et non dans toutes les colonnes).
            ->filter(function (Builder $requete): void {
                $terme = trim((string) request()->input('search.value', ''));

                if ($terme !== '') {
                    RechercheController::rechercherUtilisateur($requete, mb_substr($terme, 0, 100), $this->telephones);
                }
            })
            ->editColumn('nom', fn (Utilisateur $u): string => '<a class="font-semibold text-brand hover:underline" href="'.e(route('admin.utilisateurs.show', $u)).'">'.e($u->nomAffiche()).'</a>'
                .($u->estSuspendu() ? ' <span class="rounded-full bg-red-100 px-2 text-xs font-semibold text-red-900">suspendu</span>' : ''))
            ->editColumn('telephone', fn (Utilisateur $u): string => $u->telephone !== null ? $this->telephones->afficher($u->telephone, 'CI') : '—')
            ->addColumn('roles', fn (Utilisateur $u): string => $u->roles->pluck('name')->implode(', '))
            ->editColumn('confiance', fn (Utilisateur $u): string => $u->confiance->libelle())
            ->editColumn('cree_le', fn (Utilisateur $u): string => (string) $u->cree_le?->format('d/m/Y'))
            ->editColumn('derniere_connexion_le', fn (Utilisateur $u): string => (string) $u->derniere_connexion_le?->format('d/m/Y H:i'))
            ->rawColumns(['nom']);
    }
}
