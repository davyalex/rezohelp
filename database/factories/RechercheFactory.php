<?php

namespace Database\Factories;

use App\Models\Recherche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recherche>
 */
class RechercheFactory extends Factory
{
    protected $model = Recherche::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terme' => fake()->randomElement(['comptable', 'plombier', 'chauffeur', 'stage informatique', 'nounou']),
            'type' => null,
            'commune_id' => null,
            'nb_resultats' => fake()->numberBetween(0, 40),
            'utilisateur_id' => null,
        ];
    }
}
