<?php

namespace Database\Factories;

use App\Enums\MotifSms;
use App\Enums\StatutSms;
use App\Models\SmsEnvoye;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsEnvoye>
 */
class SmsEnvoyeFactory extends Factory
{
    protected $model = SmsEnvoye::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utilisateur_id' => null,
            'telephone' => '+22507'.fake()->numerify('########'),
            'message' => 'Resohelph: Electricien dispo Cocody, 5000F. Voir: resohelph.ci/p/ab12cd',
            'motif' => MotifSms::Alerte,
            'fournisseur' => 'log',
            'statut' => StatutSms::Envoye,
            'reference' => 'LOG-'.fake()->bothify('????????'),
            'cout_fcfa' => 0,
            'envoye_le' => now(),
        ];
    }
}
