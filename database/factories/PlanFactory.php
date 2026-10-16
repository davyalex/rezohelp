<?php

namespace Database\Factories;

use App\Enums\CibleOffre;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan_'.fake()->unique()->lexify('??????'),
            'nom' => 'Plan '.fake()->word(),
            'description' => fake()->sentence(),
            'cible' => CibleOffre::Membre,
            'famille' => null,
            'prix_fcfa' => fake()->randomElement([300, 1000, 3000, 15000, 50000]),
            'duree_jours' => 30,
            'duree_essai_jours' => 0,
            'est_defaut' => false,
            'actif' => true,
            'visible' => true,
            'ordre' => fake()->numberBetween(1, 20),
        ];
    }

    public function parDefaut(CibleOffre $cible): static
    {
        return $this->state(fn (): array => [
            'cible' => $cible,
            'prix_fcfa' => 0,
            'est_defaut' => true,
        ]);
    }

    public function avecEssai(int $jours, string $famille): static
    {
        return $this->state(fn (): array => ['duree_essai_jours' => $jours, 'famille' => $famille]);
    }
}
