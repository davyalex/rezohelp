<?php

namespace App\Models;

use App\Enums\TypeMiseEnAvant;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\MiseEnAvantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Visibilité payante d'une publication : remontée, « À la une », « Sponsorisé » (§14.9).
 */
class MiseEnAvant extends Model
{
    /** @use HasFactory<MiseEnAvantFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'mises_en_avant';

    /**
     * @var list<string>
     */
    protected $fillable = ['publication_id', 'commande_id', 'type', 'commune_id', 'debut_le', 'fin_le'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeMiseEnAvant::class,
            'debut_le' => 'datetime',
            'fin_le' => 'datetime',
            'suspendue_le' => 'datetime',
            'derniere_impression_le' => 'datetime',
            'nb_impressions' => 'integer',
            'nb_clics' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
