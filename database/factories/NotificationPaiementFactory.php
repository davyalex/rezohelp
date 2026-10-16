<?php

namespace Database\Factories;

use App\Models\NotificationPaiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPaiement>
 */
class NotificationPaiementFactory extends Factory
{
    protected $model = NotificationPaiement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = 'CMD-'.now()->year.'-'.fake()->numerify('######');

        return [
            'passerelle' => 'fake',
            'reference' => $reference,
            'charge_utile' => ['reference' => $reference],
            'signature_valide' => true,
        ];
    }
}
