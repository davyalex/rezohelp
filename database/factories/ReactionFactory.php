<?php

namespace Database\Factories;

use App\Enums\TypeReaction;
use App\Models\Publication;
use App\Models\Reaction;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->publiee(),
            'utilisateur_id' => Utilisateur::factory(),
            'type' => TypeReaction::Jaime,
        ];
    }
}
