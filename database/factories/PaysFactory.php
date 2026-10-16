<?php

namespace Database\Factories;

use App\Models\Pays;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pays>
 */
class PaysFactory extends Factory
{
    protected $model = Pays::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['SN', 'ML', 'BF', 'BJ', 'TG', 'GN', 'NE', 'CM', 'FR', 'GH', 'NG', 'MA']),
            'nom' => fake()->country(),
            'indicatif' => '+'.fake()->numberBetween(200, 299),
            'actif' => true,
            'ordre' => fake()->numberBetween(2, 50),
        ];
    }

    /**
     * Pays par défaut des comptes et des communes, créé à la demande.
     */
    public static function coteDivoire(): Pays
    {
        return Pays::query()->firstOrCreate(
            ['code' => 'CI'],
            ['nom' => 'Côte d’Ivoire', 'indicatif' => '+225', 'actif' => true, 'ordre' => 1],
        );
    }
}
