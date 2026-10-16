<?php

namespace Database\Factories;

use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Plan;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Abonnement>
 */
class AbonnementFactory extends Factory
{
    protected $model = Abonnement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulaire_type' => 'utilisateur',
            'titulaire_id' => Utilisateur::factory(),
            'plan_id' => Plan::factory(),
            'statut' => StatutAbonnement::Actif,
            'origine' => OrigineAbonnement::Paiement,
            'debut_le' => now()->subDays(5),
            'fin_le' => now()->addDays(25),
            'commande_id' => null,
            'motif' => null,
        ];
    }

    public function essai(): static
    {
        return $this->state(fn (): array => [
            'statut' => StatutAbonnement::Essai,
            'origine' => OrigineAbonnement::Essai,
        ]);
    }
}
