<?php

namespace Database\Factories;

use App\Models\Commune;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commune>
 */
class CommuneFactory extends Factory
{
    protected $model = Commune::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = fake()->unique()->city();

        return [
            'pays_code' => fn (): string => PaysFactory::coteDivoire()->code,
            'ville' => 'Abidjan',
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(4)),
            'actif' => true,
            'ordre' => fake()->numberBetween(1, 50),
        ];
    }
}
