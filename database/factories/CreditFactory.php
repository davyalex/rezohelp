<?php

namespace Database\Factories;

use App\Enums\CleDroit;
use App\Models\Credit;
use App\Models\Entreprise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credit>
 */
class CreditFactory extends Factory
{
    protected $model = Credit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulaire_type' => 'entreprise',
            'titulaire_id' => Entreprise::factory(),
            'cle' => CleDroit::EmploiOffresMois,
            'quantite' => 1,
            'consommee' => 0,
            'expire_le' => now()->addDays(30),
            'commande_id' => null,
        ];
    }
}
