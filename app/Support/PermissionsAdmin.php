<?php

namespace App\Support;

/**
 * Lecture du catalogue des rôles et permissions du back-office (config/admin.php, D-066).
 * Les noms des trois rôles livrés restent des constantes : le code en dépend.
 */
final class PermissionsAdmin
{
    /** Tous les droits, y compris la gestion de l'équipe ; ni modifiable ni supprimable. */
    public const string ROLE_SUPER = 'super_administrateur';

    public const string ROLE_ADMIN = 'administrateur';

    public const string ROLE_MODERATEUR = 'moderateur';

    /** Rôles livrés par le seeder : leurs permissions se règlent, mais ils ne se suppriment pas. */
    public const array ROLES_SYSTEME = [self::ROLE_SUPER, self::ROLE_ADMIN, self::ROLE_MODERATEUR];

    /**
     * @return array<string, array{groupe: string, libelle: string, roles: list<string>}>
     */
    public static function catalogue(): array
    {
        /** @var array<string, array{groupe: string, libelle: string, roles: list<string>}> $catalogue */
        $catalogue = config('admin.permissions', []);

        return $catalogue;
    }

    /**
     * @return list<string>
     */
    public static function cles(): array
    {
        return array_keys(self::catalogue());
    }

    public static function libelle(string $cle): string
    {
        return self::catalogue()[$cle]['libelle'] ?? $cle;
    }

    /**
     * @return array<string, list<string>> groupe => clés
     */
    public static function parGroupe(): array
    {
        $groupes = [];
        foreach (self::catalogue() as $cle => $permission) {
            $groupes[$permission['groupe']][] = $cle;
        }

        return $groupes;
    }

    /**
     * Permissions données à un rôle livré lors de sa création (le super administrateur les a toutes).
     *
     * @return list<string>
     */
    public static function parDefaut(string $role): array
    {
        if ($role === self::ROLE_SUPER) {
            return self::cles();
        }

        return array_keys(array_filter(self::catalogue(), fn (array $permission): bool => in_array($role, $permission['roles'], true)));
    }

    public static function libelleRole(string $role): string
    {
        $libelle = config('admin.roles.'.$role);

        return is_string($libelle) ? $libelle : ucfirst(str_replace('_', ' ', $role));
    }
}
