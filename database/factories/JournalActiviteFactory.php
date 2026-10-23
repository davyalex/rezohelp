<?php

namespace Database\Factories;

use App\Models\Administrateur;
use App\Models\JournalActivite;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalActivite>
 */
class JournalActiviteFactory extends Factory
{
    protected $model = JournalActivite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'administrateur_id' => Administrateur::factory(),
            'action' => 'publication.validee',
            'sujet_type' => 'publication',
            'sujet_id' => Publication::factory(),
            'donnees' => ['avant' => ['statut' => 'en_attente'], 'apres' => ['statut' => 'publiee']],
            'ip' => fake()->ipv4(),
        ];
    }
}
