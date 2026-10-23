<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererRole;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * L — Rôles de l'équipe et leurs permissions (D-066), permission roles.gerer.
 */
class RoleController extends ControleurAdmin
{
    public function __construct(private readonly GererRole $gerer) {}

    public function index(): View
    {
        $roles = $this->roles()->withCount('permissions')->orderBy('id')->get();
        $comptes = DB::table('model_has_roles')
            ->where('model_type', (new Administrateur)->getMorphClass())
            ->whereIn('role_id', $roles->pluck('id'))
            ->selectRaw('role_id, count(*) as nombre')->groupBy('role_id')
            ->pluck('nombre', 'role_id');

        return view('admin.roles.index', ['roles' => $roles, 'comptes' => $comptes, 'total' => count(PermissionsAdmin::cles())]);
    }

    public function create(): View
    {
        return view('admin.roles.formulaire', ['role' => new Role, 'cochees' => []]);
    }

    public function store(RoleRequest $requete): RedirectResponse
    {
        try {
            $role = $this->gerer->creer($requete->string('libelle')->trim()->toString(), $requete->permissions(), $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.roles.edit', $role)->with('statut', 'Rôle créé.');
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === Administrateur::GARDE, 404);

        return view('admin.roles.formulaire', [
            'role' => $role,
            'cochees' => $role->name === PermissionsAdmin::ROLE_SUPER ? PermissionsAdmin::cles() : $role->permissions()->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $requete, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === Administrateur::GARDE, 404);

        return $this->appliquer(fn () => $this->gerer->modifier($role, $requete->permissions(), $this->admin($requete)), 'Permissions enregistrées.');
    }

    public function destroy(Request $requete, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === Administrateur::GARDE, 404);

        try {
            $this->gerer->supprimer($role, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.roles.index')->with('statut', 'Rôle supprimé.');
    }

    /**
     * @return Builder<Role>
     */
    private function roles(): Builder
    {
        return Role::query()->where('guard_name', Administrateur::GARDE);
    }
}
