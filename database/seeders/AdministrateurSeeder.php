<?php

namespace Database\Seeders;

use App\Actions\Admin\GererAdministrateur;
use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Database\Seeder;

/**
 * Premier compte de l'équipe : super administrateur (D-066), réglé dans .env
 * (ADMIN_NOM_UTILISATEUR, ADMIN_NOM, ADMIN_PRENOMS, ADMIN_EMAIL, ADMIN_MOT_DE_PASSE) via config/admin.php.
 * Sans mot de passe, un mot de passe de 8 caractères est généré et affiché une seule fois dans la console.
 * Il doit être changé à la première connexion. Un compte existant n'est jamais modifié.
 */
class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{nom_utilisateur: ?string, nom: ?string, prenoms: ?string, email: ?string, mot_de_passe: ?string} $config */
        $config = config('admin.super_administrateur');
        $nomUtilisateur = mb_strtolower(trim((string) $config['nom_utilisateur']));

        if ($nomUtilisateur === '') {
            $this->command->warn('ADMIN_NOM_UTILISATEUR absent : compte super administrateur non créé.');

            return;
        }

        $admin = Administrateur::withTrashed()->where('nom_utilisateur', $nomUtilisateur)->first();

        if ($admin === null) {
            $motDePasse = (string) $config['mot_de_passe'];
            $genere = $motDePasse === '';
            $motDePasse = $genere ? GererAdministrateur::genererMotDePasse() : $motDePasse;

            $admin = new Administrateur([
                'nom' => (string) ($config['nom'] ?: 'Administrateur'),
                'prenoms' => $config['prenoms'] ?: null,
                'nom_utilisateur' => $nomUtilisateur,
                'email' => $config['email'] ?: null,
                'mot_de_passe' => $motDePasse,
            ]);
            $admin->doit_changer_mot_de_passe = true;
            $admin->save();

            $this->command->info("Super administrateur « {$nomUtilisateur} » créé (connexion sur /admin/connexion).");
            if ($genere) {
                $this->command->warn("Mot de passe provisoire (affiché une seule fois) : {$motDePasse}");
            }
        }

        $admin->assignRole(PermissionsAdmin::ROLE_SUPER);
    }
}
