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

        $nouvelles = [];
        foreach (PermissionsAdmin::cles() as $cle) {
            if (! Permission::query()->where('name', $cle)->where('guard_name', Administrateur::GARDE)->exists()) {
                $nouvelles[] = $cle;
            }
            Permission::findOrCreate($cle, Administrateur::GARDE);
        }

        // Le super administrateur a toujours toutes les permissions (et Gate::before le confirme).
        Role::findOrCreate(PermissionsAdmin::ROLE_SUPER, Administrateur::GARDE)->syncPermissions(PermissionsAdmin::cles());

        // Permissions par défaut des autres rôles livrés : config/admin.php (« roles » de chaque permission).
        // Rôle déjà créé : on ne touche pas aux réglages faits dans l'admin, on lui donne seulement
        // les permissions qui viennent d'être ajoutées au catalogue.
        foreach ([PermissionsAdmin::ROLE_ADMIN, PermissionsAdmin::ROLE_MODERATEUR] as $nom) {
            $this->creerOuCompleter($nom, PermissionsAdmin::parDefaut($nom), $nouvelles);
        }

        foreach (self::ROLES_MEMBRES as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $parDefaut  permissions par défaut du rôle
     * @param  list<string>  $nouvelles  permissions créées pendant ce passage
     */
    private function creerOuCompleter(string $nom, array $parDefaut, array $nouvelles): void
    {
        $existe = Role::query()->where('name', $nom)->where('guard_name', Administrateur::GARDE)->exists();
        $role = Role::findOrCreate($nom, Administrateur::GARDE);

        if (! $existe) {
            $role->syncPermissions($parDefaut);

            return;
        }

        $aAjouter = array_values(array_intersect($parDefaut, $nouvelles));
        if ($aAjouter !== []) {
            $role->givePermissionTo($aAjouter);
        }
    }
}
