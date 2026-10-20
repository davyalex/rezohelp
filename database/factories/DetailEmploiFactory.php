<?php

namespace Database\Factories;

use App\Enums\ModeCandidature;
use App\Enums\TypeContrat;
use App\Models\DetailEmploi;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetailEmploi>
 */
class DetailEmploiFactory extends Factory
{
    protected $model = DetailEmploi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salaireMin = fake()->numberBetween(8, 40) * 10000;

        return [
            'publication_id' => Publication::factory()->emploi(),
            'employeur_particulier' => false,
            'type_contrat' => fake()->randomElement(TypeContrat::cases()),
            'nb_postes' => fake()->numberBetween(1, 5),
            'salaire_min' => $salaireMin,
            'salaire_max' => $salaireMin + fake()->numberBetween(0, 20) * 10000,
            'salaire_a_negocier' => false,
            'devise' => 'XOF',
            'experience_requise' => fake()->randomElement(['Débutant accepté', '1 à 2 ans', '3 à 5 ans', 'Plus de 5 ans']),
            'niveau_etude' => fake()->randomElement(['BEPC', 'BAC', 'BTS', 'Licence', 'Master']),
            'date_limite' => now()->addDays(fake()->numberBetween(7, 30)),
            'mode_candidature' => ModeCandidature::Rezohelp,
            'contact_candidature' => null,
        ];
    }
}
