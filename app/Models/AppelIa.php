<?php

namespace App\Models;

use App\Enums\StatutAppelIa;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\AppelIaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des appels à l'API d'IA (tokens et coût estimé).
 */
class AppelIa extends Model
{
    /** @use HasFactory<AppelIaFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'appels_ia';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publication_id',
        'import_veille_id',
        'modele',
        'tokens_entree',
        'tokens_sortie',
        'cout_estime_fcfa',
        'duree_ms',
        'statut',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens_entree' => 'integer',
            'tokens_sortie' => 'integer',
            'cout_estime_fcfa' => 'integer',
            'duree_ms' => 'integer',
            'statut' => StatutAppelIa::class,
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
     * @return BelongsTo<ImportVeille, $this>
     */
    public function importVeille(): BelongsTo
    {
        return $this->belongsTo(ImportVeille::class);
    }
}
