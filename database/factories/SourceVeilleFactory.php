<?php

namespace Database\Factories;

use App\Enums\NiveauConfiance;
use App\Models\SourceVeille;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceVeille>
 */
class SourceVeilleFactory extends Factory
{
    protected $model = SourceVeille::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'domaine' => fake()->unique()->domainName(),
            'niveau_confiance' => NiveauConfiance::Moyen,
            'publication_auto' => false,
            'actif' => true,
        ];
    }
}
