<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions (CONTEXTE §2, §13.2).
 */
class RolesEtPermissionsSeeder extends Seeder
{
    /**
     * Permission => accordée au modérateur ?
     *
     * @var array<string, bool>
     */
    public const array PERMISSIONS = [
        'tableau_de_bord.voir' => true,
        'publications.moderer' => true,
        'publications.gerer' => true,
        'signalements.traiter' => true,
        'commentaires.moderer' => true,
        'utilisateurs.voir' => true,
        'utilisateurs.suspendre' => true,
        'utilisateurs.gerer' => false,
        'entreprises.verifier' => false,
        'veille.gerer' => false,
        'referentiels.gerer' => false,
        'parametres.gerer' => false,
        'statistiques.voir' => true,
        'systeme.voir' => false,
        'abonnements.gerer' => false,
        'paiements.voir' => false,
    ];

    /**
     * Rôles fonctionnels, sans permission d'administration : attribués
     * automatiquement à la première publication de chaque type (§11.1).
     */
    public const array ROLES_MEMBRES = ['membre', 'recruteur', 'artisan', 'organisme'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $nom) {
            Permission::findOrCreate($nom, 'web');
        }

        Role::findOrCreate('administrateur', 'web')->syncPermissions(array_keys(self::PERMISSIONS));
        Role::findOrCreate('moderateur', 'web')->syncPermissions(array_keys(array_filter(self::PERMISSIONS)));

        foreach (self::ROLES_MEMBRES as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
