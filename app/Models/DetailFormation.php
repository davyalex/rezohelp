<?php

namespace App\Models;

use App\Enums\Modalite;
use App\Enums\ModeInscription;
use Database\Factories\DetailFormationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détails d'une formation (1–1 avec publications).
 */
class DetailFormation extends Model
{
    /** @use HasFactory<DetailFormationFactory> */
    use HasFactory;

    protected $table = 'details_formation';

    protected $primaryKey = 'publication_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publication_id',
        'organisme',
        'modalite',
        'duree',
        'date_debut',
        'prix',
        'certifiante',
        'places',
        'mode_inscription',
        'contact_inscription',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modalite' => Modalite::class,
            'date_debut' => 'date',
            'prix' => 'integer',
            'certifiante' => 'boolean',
            'places' => 'integer',
            'mode_inscription' => ModeInscription::class,
        ];
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
