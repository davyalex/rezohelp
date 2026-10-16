<?php

namespace Database\Factories;

use App\Enums\MotifSignalement;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    protected $model = Signalement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->publiee(),
            'utilisateur_id' => Utilisateur::factory(),
            'motif' => MotifSignalement::Arnaque,
            'commentaire' => fake()->optional()->sentence(),
        ];
    }
}
