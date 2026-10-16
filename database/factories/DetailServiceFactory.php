<?php

namespace Database\Factories;

use App\Enums\UniteTarif;
use App\Models\DetailService;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetailService>
 */
class DetailServiceFactory extends Factory
{
    protected $model = DetailService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $telephone = '+22507'.fake()->numerify('########');

        return [
            'publication_id' => Publication::factory()->serviceOffre(),
            'metier' => fake()->randomElement(['Plombier', 'Électricien', 'Ménagère', 'Nounou', 'Chauffeur', 'Mécanicien', 'Couturier']),
            'tarif_indicatif' => fake()->numberBetween(2, 30) * 1000,
            'unite_tarif' => fake()->randomElement([UniteTarif::Heure, UniteTarif::Jour, UniteTarif::Prestation]),
            'zones_intervention' => [],
            'disponibilite' => fake()->randomElement(['Tous les jours', 'Du lundi au samedi', 'Le week-end']),
            'telephone_contact' => $telephone,
            'afficher_telephone' => true,
            'whatsapp' => $telephone,
        ];
    }

    public function demande(): static
    {
        return $this->state(fn (): array => [
            'publication_id' => Publication::factory()->serviceDemande(),
            'tarif_indicatif' => null,
            'unite_tarif' => null,
            'telephone_contact' => null,
            'afficher_telephone' => false,
            'whatsapp' => null,
            'date_souhaitee' => now()->addDays(fake()->numberBetween(0, 7)),
            'budget' => fake()->boolean() ? fake()->numberBetween(5, 50) * 1000 : null,
        ]);
    }
}
