<?php

namespace Database\Factories;

use App\Models\EssaiUtilise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EssaiUtilise>
 */
class EssaiUtiliseFactory extends Factory
{
    protected $model = EssaiUtilise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empreinte_telephone' => hash('sha256', '+22507'.fake()->unique()->numerify('########')),
            'famille' => 'alertes_sms',
            'utilise_le' => now(),
        ];
    }
}
