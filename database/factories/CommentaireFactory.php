<?php

namespace Database\Factories;

use App\Enums\StatutCommentaire;
use App\Models\Commentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commentaire>
 */
class CommentaireFactory extends Factory
{
    protected $model = Commentaire::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory()->publiee(),
            'utilisateur_id' => Utilisateur::factory(),
            'parent_id' => null,
            'contenu' => fake()->sentence(),
            'statut' => StatutCommentaire::Visible,
        ];
    }
}
