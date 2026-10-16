<?php

namespace Database\Factories;

use App\Enums\TypePublication;
use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categorie>
 */
class CategorieFactory extends Factory
{
    protected $model = Categorie::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = Str::ucfirst(fake()->unique()->words(2, true));

        return [
            'type' => TypePublication::Emploi,
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(4)),
            'icone' => '💼',
            'parent_id' => null,
            'ordre' => fake()->numberBetween(1, 50),
            'actif' => true,
        ];
    }

    public function duType(TypePublication $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
