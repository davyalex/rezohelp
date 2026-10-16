<?php

namespace Database\Factories;

use App\Enums\StatutAppelIa;
use App\Models\AppelIa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppelIa>
 */
class AppelIaFactory extends Factory
{
    protected $model = AppelIa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => null,
            'import_veille_id' => null,
            'modele' => 'fake',
            'tokens_entree' => fake()->numberBetween(300, 900),
            'tokens_sortie' => fake()->numberBetween(80, 250),
            'cout_estime_fcfa' => fake()->numberBetween(1, 5),
            'duree_ms' => fake()->numberBetween(400, 2500),
            'statut' => StatutAppelIa::Succes,
        ];
    }
}
