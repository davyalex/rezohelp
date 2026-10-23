<?php

namespace App\DataTables;

use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * Comptes de l'équipe (D-066).
 *
 * @extends TableAdmin<Administrateur>
 */
class AdministrateursDataTable extends TableAdmin
{
    protected function requete(Request $requete): Builder
    {
        return Administrateur::query()
            ->select(['id', 'nom', 'prenoms', 'nom_utilisateur', 'email', 'doit_changer_mot_de_passe', 'suspendu_le', 'derniere_connexion_le', 'cree_le'])
            ->with('roles:id,name')
            ->when($requete->filled('role'), fn (Builder $r) => $r->role($requete->string('role')->toString()))
            ->when($requete->input('etat') === 'actif', fn (Builder $r) => $r->whereNull('suspendu_le'))
            ->when($requete->input('etat') === 'suspendu', fn (Builder $r) => $r->whereNotNull('suspendu_le'));
    }

    public function colonnes(): array
    {
        return [
            ['data' => 'nom', 'titre' => 'Nom', 'recherche' => true],
            ['data' => 'nom_utilisateur', 'titre' => 'Nom d’utilisateur', 'recherche' => true],
            ['data' => 'role', 'titre' => 'Rôle', 'tri' => false],
            ['data' => 'etat', 'titre' => 'État', 'tri' => false],
            ['data' => 'derniere_connexion_le', 'titre' => 'Dernière connexion'],
            ['data' => 'actions', 'titre' => 'Actions', 'tri' => false, 'classe' => 'text-right w-16'],
        ];
    }

    public function tri(): array
    {
        return [0, 'asc'];
    }

    protected function formater(EloquentDataTable $table): EloquentDataTable
    {
        $connecte = request()->user(Administrateur::GARDE);

        return $table
            ->editColumn('nom', fn (Administrateur $a): string => '<span class="flex items-center gap-2"><span aria-hidden="true" class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-clair text-xs font-bold text-brand-fonce">'.e($a->initiales()).'</span><span><span class="block font-semibold text-slate-900">'.e($a->nomComplet()).'</span>'
                .($a->email ? '<span class="block text-xs text-slate-500">'.e($a->email).'</span>' : '').'</span></span>')
            ->editColumn('nom_utilisateur', fn (Administrateur $a): string => '<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">'.e($a->nom_utilisateur).'</code>')
            ->addColumn('role', fn (Administrateur $a): string => view('admin.administrateurs._role', ['role' => $a->getRoleNames()->first()])->render())
            ->addColumn('etat', fn (Administrateur $a): string => match (true) {
                $a->estSuspendu() => '<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Suspendu</span>',
                $a->doit_changer_mot_de_passe => '<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Mot de passe à changer</span>',
                default => '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Actif</span>',
            })
            ->editColumn('derniere_connexion_le', fn (Administrateur $a): string => $a->derniere_connexion_le?->diffForHumans() ?? 'Jamais')
            ->addColumn('actions', fn (Administrateur $a): string => view('admin.administrateurs._actions', [
                'administrateur' => $a,
                'soiMeme' => $connecte instanceof Administrateur && $connecte->is($a),
                'protege' => $a->roles->contains('name', PermissionsAdmin::ROLE_SUPER) && ! ($connecte instanceof Administrateur && $connecte->estSuperAdministrateur()),
            ])->render())
            ->rawColumns(['nom', 'nom_utilisateur', 'role', 'etat', 'actions']);
    }
}
