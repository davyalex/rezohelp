<?php

namespace Database\Factories;

use App\Enums\StatutReponse;
use App\Enums\TypeReponse;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reponse>
 */
class ReponseFactory extends Factory
{
    protected $model = Reponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->emploi()->publiee(),
            'utilisateur_id' => Utilisateur::factory(),
            'type' => TypeReponse::Candidature,
            'message' => fake()->optional()->sentence(),
            'telephone_partage' => true,
            'statut' => StatutReponse::Envoyee,
        ];
    }
}
