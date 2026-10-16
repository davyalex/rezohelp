<?php

namespace Database\Factories;

use App\Models\Entreprise;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entreprise>
 */
class EntrepriseFactory extends Factory
{
    protected $model = Entreprise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = fake()->company();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(5)),
            'secteur' => fake()->randomElement(['Commerce', 'BTP', 'Informatique', 'Santé', 'Finance', 'Formation']),
            'site_web' => fake()->optional()->url(),
            'telephone' => '+22505'.fake()->numerify('########'),
            'commune_id' => null,
            'verifiee' => false,
            'proprietaire_id' => Utilisateur::factory(),
        ];
    }

    public function verifiee(): static
    {
        return $this->state(fn (): array => ['verifiee' => true]);
    }
}
