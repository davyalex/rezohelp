<?php

namespace Database\Seeders;

use App\Models\Pays;
use Illuminate\Database\Seeder;

/**
 * Pays du sélecteur d'indicatif (CONTEXTE §9). Côte d'Ivoire en premier, par défaut.
 */
class PaysSeeder extends Seeder
{
    public function run(): void
    {
        $pays = [
            ['CI', 'Côte d’Ivoire', '+225'],
            ['SN', 'Sénégal', '+221'],
            ['ML', 'Mali', '+223'],
            ['BF', 'Burkina Faso', '+226'],
            ['BJ', 'Bénin', '+229'],
            ['TG', 'Togo', '+228'],
            ['GN', 'Guinée', '+224'],
            ['NE', 'Niger', '+227'],
            ['CM', 'Cameroun', '+237'],
            ['FR', 'France', '+33'],
        ];

        foreach ($pays as $ordre => [$code, $nom, $indicatif]) {
            Pays::query()->updateOrCreate(
                ['code' => $code],
                ['nom' => $nom, 'indicatif' => $indicatif, 'actif' => true, 'ordre' => $ordre + 1],
            );
        }
    }
}
