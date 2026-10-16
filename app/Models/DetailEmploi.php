<?php

namespace App\Models;

use App\Enums\ModeCandidature;
use App\Enums\TypeContrat;
use Database\Factories\DetailEmploiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détails d'une offre d'emploi (1–1 avec publications).
 */
class DetailEmploi extends Model
{
    /** @use HasFactory<DetailEmploiFactory> */
    use HasFactory;

    protected $table = 'details_emploi';

    protected $primaryKey = 'publication_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publication_id',
        'employeur_particulier',
        'type_contrat',
        'nb_postes',
        'salaire_min',
        'salaire_max',
        'salaire_a_negocier',
        'devise',
        'experience_requise',
        'niveau_etude',
        'date_limite',
        'mode_candidature',
        'contact_candidature',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employeur_particulier' => 'boolean',
            'type_contrat' => TypeContrat::class,
            'nb_postes' => 'integer',
            'salaire_min' => 'integer',
            'salaire_max' => 'integer',
            'salaire_a_negocier' => 'boolean',
            'date_limite' => 'date',
            'mode_candidature' => ModeCandidature::class,
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
