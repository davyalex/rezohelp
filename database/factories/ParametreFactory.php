<?php

namespace Database\Factories;

use App\Enums\TypeParametre;
use App\Models\Parametre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parametre>
 */
class ParametreFactory extends Factory
{
    protected $model = Parametre::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cle' => 'essai_'.fake()->unique()->lexify('????????'),
            'valeur' => (string) fake()->numberBetween(1, 100),
            'type' => TypeParametre::Entier,
            'libelle' => fake()->sentence(3),
        ];
    }
}
