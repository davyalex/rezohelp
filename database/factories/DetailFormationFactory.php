<?php

namespace Database\Factories;

use App\Enums\Modalite;
use App\Enums\ModeInscription;
use App\Models\DetailFormation;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetailFormation>
 */
class DetailFormationFactory extends Factory
{
    protected $model = DetailFormation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->formation(),
            'organisme' => fake()->company(),
            'modalite' => fake()->randomElement(Modalite::cases()),
            'duree' => fake()->randomElement(['2 jours', '1 semaine', '1 mois', '3 mois', '6 mois']),
            'date_debut' => now()->addDays(fake()->numberBetween(5, 60)),
            'prix' => fake()->randomElement([0, 25000, 50000, 150000, 300000]),
            'certifiante' => fake()->boolean(),
            'places' => fake()->optional()->numberBetween(10, 40),
            'mode_inscription' => ModeInscription::Resohelph,
            'contact_inscription' => null,
        ];
    }
}
