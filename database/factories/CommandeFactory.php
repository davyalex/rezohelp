<?php

namespace Database\Factories;

use App\Enums\PasserelleCommande;
use App\Enums\StatutCommande;
use App\Enums\TypeArticle;
use App\Models\Commande;
use App\Models\Plan;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commande>
 */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $utilisateur = Utilisateur::factory();

        return [
            'numero' => 'CMD-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'utilisateur_id' => $utilisateur,
            'titulaire_type' => 'utilisateur',
            'titulaire_id' => fn (array $attributs): mixed => $attributs['utilisateur_id'],
            'article_type' => TypeArticle::Plan,
            'article_id' => Plan::factory(),
            'publication_id' => null,
            'commune_id' => null,
            'montant_ttc_fcfa' => 1000,
            'taux_tva' => 18,
            'montant_ht_fcfa' => 847,
            'statut' => StatutCommande::EnAttente,
            'passerelle' => PasserelleCommande::Fake,
        ];
    }
}
