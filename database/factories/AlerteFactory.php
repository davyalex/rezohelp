<?php

namespace Database\Factories;

use App\Enums\FrequenceAlerte;
use App\Models\Alerte;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alerte>
 */
class AlerteFactory extends Factory
{
    protected $model = Alerte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utilisateur_id' => Utilisateur::factory(),
            'nom' => 'Alerte '.fake()->word(),
            'type' => null,
            'categorie_id' => null,
            'commune_id' => null,
            'type_contrat' => null,
            'mots_cles' => null,
            'frequence' => FrequenceAlerte::Instantanee,
            'active' => true,
        ];
    }

    public function enPause(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function quotidienne(): static
    {
        return $this->state(fn (): array => ['frequence' => FrequenceAlerte::Quotidienne]);
    }
}
