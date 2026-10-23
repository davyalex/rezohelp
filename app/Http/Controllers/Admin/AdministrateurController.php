<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererAdministrateur;
use App\DataTables\AdministrateursDataTable;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Admin\AdministrateurRequest;
use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * L — Comptes de l'équipe (D-066), permission administrateurs.gerer.
 */
class AdministrateurController extends ControleurAdmin
{
    public function __construct(private readonly GererAdministrateur $gerer) {}

    public function index(Request $requete, AdministrateursDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.administrateurs.index', [
            'table' => $table,
            'roles' => $this->rolesExistants()->mapWithKeys(fn (string $nom): array => [$nom => PermissionsAdmin::libelleRole($nom)])->all(),
        ]);
    }

    public function create(Request $requete): View
    {
        return $this->formulaire($requete, new Administrateur);
    }

    public function store(AdministrateurRequest $requete): RedirectResponse
    {
        try {
            [$admin, $motDePasse] = $this->gerer->creer($requete->donnees(), $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.administrateurs.index')
            ->with('statut', 'Compte « '.$admin->nom_utilisateur.' » créé.')
            ->with('mot_de_passe_genere', ['nom_utilisateur' => $admin->nom_utilisateur, 'mot_de_passe' => $motDePasse]);
    }

    public function edit(Request $requete, Administrateur $administrateur): View
    {
        return $this->formulaire($requete, $administrateur->load('roles:id,name'));
    }

    public function update(AdministrateurRequest $requete, Administrateur $administrateur): RedirectResponse
    {
        try {
            $this->gerer->modifier($administrateur, $requete->donnees(), $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.administrateurs.index')->with('statut', 'Compte enregistré.');
    }

    public function reinitialiserMotDePasse(Request $requete, Administrateur $administrateur): RedirectResponse
    {
        try {
            $motDePasse = $this->gerer->reinitialiserMotDePasse($administrateur, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return back()
            ->with('statut', 'Nouveau mot de passe généré.')
            ->with('mot_de_passe_genere', ['nom_utilisateur' => $administrateur->nom_utilisateur, 'mot_de_passe' => $motDePasse]);
    }

    public function suspendre(Request $requete, Administrateur $administrateur): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->suspendre($administrateur, $this->admin($requete)), 'Compte suspendu.');
    }

    public function reactiver(Request $requete, Administrateur $administrateur): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->reactiver($administrateur, $this->admin($requete)), 'Compte réactivé.');
    }

    public function destroy(Request $requete, Administrateur $administrateur): RedirectResponse
    {
        try {
            $this->gerer->supprimer($administrateur, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.administrateurs.index')->with('statut', 'Compte supprimé.');
    }

    private function formulaire(Request $requete, Administrateur $administrateur): View
    {
        $auteur = $this->admin($requete);
        $soiMeme = $administrateur->exists && $administrateur->is($auteur);
        $roleActuel = $administrateur->exists ? $administrateur->getRoleNames()->first() : PermissionsAdmin::ROLE_MODERATEUR;

        abort_if($administrateur->exists && $administrateur->estSuperAdministrateur() && ! $auteur->estSuperAdministrateur(), 403);

        $roles = Role::query()->where('guard_name', Administrateur::GARDE)->withCount('permissions')->orderBy('id')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->name => [
                'libelle' => PermissionsAdmin::libelleRole($role->name),
                'resume' => $role->name === PermissionsAdmin::ROLE_SUPER
                    ? 'Tous les droits, y compris l’équipe et les rôles.'
                    : $role->permissions_count.' permission(s)',
                'bloque' => ($soiMeme && $role->name !== $roleActuel)
                    || ($role->name === PermissionsAdmin::ROLE_SUPER && ! $auteur->estSuperAdministrateur()),
            ]])->all();

        return view('admin.administrateurs.formulaire', [
            'administrateur' => $administrateur,
            'roles' => $roles,
            'roleActuel' => $roleActuel,
            'soiMeme' => $soiMeme,
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function rolesExistants(): Collection
    {
        return Role::query()->where('guard_name', Administrateur::GARDE)->orderBy('id')->pluck('name');
    }
}
