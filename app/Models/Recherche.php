<?php

namespace App\Models;

use App\Enums\TypePublication;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\RechercheFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des recherches (statistiques, recherches sans résultat).
 */
class Recherche extends Model
{
    /** @use HasFactory<RechercheFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'recherches';

    /**
     * @var list<string>
     */
    protected $fillable = ['terme', 'type', 'commune_id', 'nb_resultats', 'utilisateur_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePublication::class,
            'nb_resultats' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return BelongsTo<Utilisateur, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
