<?php

namespace Database\Seeders;

use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions (CONTEXTE §2, §13.2, D-066).
 *  - Équipe (garde « admin ») : catalogue et permissions par défaut dans config/admin.php ;
 *    super_administrateur a toujours tout.
 *  - Membres (garde « web ») : membre, recruteur, artisan, organisme, sans permission d'équipe.
 * Les rôles déjà présents gardent les permissions réglées dans l'admin : seules les manquantes sont ajoutées
 * aux rôles livrés lors de leur création.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public const array ROLES_MEMBRES = ['membre', 'recruteur', 'artisan', 'organisme'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionsAdmin::cles() as $cle) {
            Permission::findOrCreate($cle, Administrateur::GARDE);
        }

        // Le super administrateur a toujours toutes les permissions (et Gate::before le confirme).
        Role::findOrCreate(PermissionsAdmin::ROLE_SUPER, Administrateur::GARDE)->syncPermissions(PermissionsAdmin::cles());

        // Permissions par défaut des autres rôles livrés : config/admin.php (« roles » de chaque permission).
        $this->creerSiAbsent(PermissionsAdmin::ROLE_ADMIN, PermissionsAdmin::parDefaut(PermissionsAdmin::ROLE_ADMIN));
        $this->creerSiAbsent(PermissionsAdmin::ROLE_MODERATEUR, PermissionsAdmin::parDefaut(PermissionsAdmin::ROLE_MODERATEUR));

        foreach (self::ROLES_MEMBRES as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function creerSiAbsent(string $nom, array $permissions): void
    {
        $existe = Role::query()->where('name', $nom)->where('guard_name', Administrateur::GARDE)->exists();
        $role = Role::findOrCreate($nom, Administrateur::GARDE);

        if (! $existe) {
            $role->syncPermissions($permissions);
        }
    }
}
