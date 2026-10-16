<?php

namespace App\Models;

use App\Enums\CibleOffre;
use App\Enums\TypeProduit;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\ProduitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Achat ponctuel : offre supplémentaire, remontée, mise en avant (§14.4).
 */
class Produit extends Model
{
    /** @use HasFactory<ProduitFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'produits';

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'nom', 'description', 'cible', 'type', 'prix_fcfa', 'duree_jours', 'actif', 'ordre'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cible' => CibleOffre::class,
            'type' => TypeProduit::class,
            'prix_fcfa' => 'integer',
            'duree_jours' => 'integer',
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }
}
