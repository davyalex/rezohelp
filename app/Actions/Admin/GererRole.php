<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Exceptions\OperationImpossibleException;
use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles de l'équipe et leurs permissions (garde « admin », D-066).
 * Le super administrateur a toujours tout : ni modifiable ni supprimable. Les rôles livrés
 * (administrateur, modérateur) se règlent mais ne se suppriment pas ; un rôle encore attribué non plus.
 */
class GererRole
{
    use JournaliseActivite;

    /**
     * @param  list<string>  $permissions
     */
    public function creer(string $libelle, array $permissions, Administrateur $auteur): Role
    {
        $nom = Str::slug($libelle, '_');

        if ($nom === '' || Role::query()->where('guard_name', Administrateur::GARDE)->where('name', $nom)->exists()) {
            throw new OperationImpossibleException('Un rôle porte déjà ce nom.');
        }

        return DB::transaction(function () use ($nom, $permissions, $auteur): Role {
            $role = new Role(['name' => $nom, 'guard_name' => Administrateur::GARDE]);
            $role->save();
            $role->syncPermissions($this->filtrer($permissions));
            $this->journaliser($auteur, 'role.cree', $role, ['permissions' => $this->filtrer($permissions)]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public function modifier(Role $role, array $permissions, Administrateur $auteur): void
    {
        if ($role->name === PermissionsAdmin::ROLE_SUPER) {
            throw new OperationImpossibleException('Le super administrateur a toujours toutes les permissions.');
        }

        $avant = $role->permissions()->pluck('name')->sort()->values()->all();
        $apres = collect($this->filtrer($permissions))->sort()->values()->all();

        if ($avant === $apres) {
            return;
        }

        DB::transaction(function () use ($role, $auteur, $avant, $apres): void {
            $role->syncPermissions($apres);
            $this->journaliser($auteur, 'role.modifie', $role, ['avant' => $avant, 'apres' => $apres]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function supprimer(Role $role, Administrateur $auteur): void
    {
        if (in_array($role->name, PermissionsAdmin::ROLES_SYSTEME, true)) {
            throw new OperationImpossibleException('Ce rôle est livré avec l’application : il ne peut pas être supprimé.');
        }

        if (Administrateur::role($role->name)->exists()) {
            throw new OperationImpossibleException('Ce rôle est encore attribué : changez d’abord le rôle des comptes concernés.');
        }

        DB::transaction(function () use ($role, $auteur): void {
            $this->journaliser($auteur, 'role.supprime', null, ['role' => $role->name]);
            $role->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * Seules les permissions du catalogue sont gardées.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function filtrer(array $permissions): array
    {
        return array_values(array_intersect(PermissionsAdmin::cles(), $permissions));
    }
}
