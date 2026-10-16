<?php

namespace Database\Factories;

use App\Enums\TypeMiseEnAvant;
use App\Models\MiseEnAvant;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MiseEnAvant>
 */
class MiseEnAvantFactory extends Factory
{
    protected $model = MiseEnAvant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->publiee(),
            'commande_id' => null,
            'type' => TypeMiseEnAvant::SponsoriseFil,
            'commune_id' => null,
            'debut_le' => now()->subDay(),
            'fin_le' => now()->addDays(6),
        ];
    }
}
