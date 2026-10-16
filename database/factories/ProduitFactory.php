<?php

namespace Database\Factories;

use App\Enums\CibleOffre;
use App\Enums\TypeProduit;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'produit_'.fake()->unique()->lexify('??????'),
            'nom' => 'Remontée',
            'description' => fake()->sentence(),
            'cible' => CibleOffre::Artisan,
            'type' => TypeProduit::Remontee,
            'prix_fcfa' => 500,
            'duree_jours' => null,
            'actif' => true,
            'ordre' => 1,
        ];
    }
}
