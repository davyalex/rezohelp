<?php

namespace Database\Factories;

use App\Enums\StatutImportVeille;
use App\Models\ImportVeille;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportVeille>
 */
class ImportVeilleFactory extends Factory
{
    protected $model = ImportVeille::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fichier_drive_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{28}'),
            'nom_fichier' => 'veille-'.fake()->date().'.csv',
            'statut' => StatutImportVeille::Termine,
            'nb_lignes' => 20,
            'nb_importees' => 15,
            'nb_doublons' => 4,
            'nb_rejetees' => 1,
            'traite_le' => now(),
        ];
    }
}
