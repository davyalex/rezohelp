<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Illuminate\Database\Seeder;

/**
 * Compte administrateur initial, lu depuis .env (ADMIN_TELEPHONE, ADMIN_MOT_DE_PASSE).
 */
class AdministrateurSeeder extends Seeder
{
    public function __construct(private readonly TelephoneService $telephones) {}

    public function run(): void
    {
        $saisie = config('rezohelp.admin.telephone');
        $motDePasse = config('rezohelp.admin.mot_de_passe');
        $pays = (string) config('rezohelp.admin.pays_code');

        if (! is_string($saisie) || $saisie === '' || ! is_string($motDePasse) || $motDePasse === '') {
            $this->command->warn('ADMIN_TELEPHONE ou ADMIN_MOT_DE_PASSE absent : compte administrateur non créé.');

            return;
        }

        $telephone = $this->telephones->normaliser($saisie, $pays);

        if ($telephone === null) {
            $this->command->error("ADMIN_TELEPHONE invalide pour le pays {$pays} : compte administrateur non créé.");

            return;
        }

        $admin = Utilisateur::query()->firstOrNew(['telephone' => $telephone]);

        if (! $admin->exists) {
            $admin->fill([
                'nom' => (string) config('rezohelp.admin.nom'),
                'pays_code' => $pays,
                'telephone' => $telephone,
                'mot_de_passe' => $motDePasse,
            ]);
            $admin->telephone_verifie_le = now();
            $admin->save();
        }

        $admin->assignRole('administrateur');
    }
}
