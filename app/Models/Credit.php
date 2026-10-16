<?php

namespace App\Models;

use App\Enums\CleDroit;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\CreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Crédit ponctuel issu d'un produit « offre supplémentaire ».
 */
class Credit extends Model
{
    /** @use HasFactory<CreditFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'credits';

    /**
     * @var list<string>
     */
    protected $fillable = ['titulaire_type', 'titulaire_id', 'cle', 'quantite', 'expire_le', 'commande_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cle' => CleDroit::class,
            'quantite' => 'integer',
            'consommee' => 'integer',
            'expire_le' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function titulaire(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
