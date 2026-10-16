<?php

namespace Database\Factories;

use App\Enums\CleDroit;
use App\Models\Plan;
use App\Models\PlanLimite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanLimite>
 */
class PlanLimiteFactory extends Factory
{
    protected $model = PlanLimite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'cle' => CleDroit::AlertesMax,
            'valeur' => '10',
        ];
    }
}
