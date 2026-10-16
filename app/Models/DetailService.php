<?php

namespace App\Models;

use App\Casts\TelephoneE164;
use App\Enums\UniteTarif;
use Database\Factories\DetailServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détails d'une annonce de service, offre ou demande (1–1 avec publications).
 * Seul telephone_contact (si afficher_telephone) est public.
 */
class DetailService extends Model
{
    /** @use HasFactory<DetailServiceFactory> */
    use HasFactory;

    protected $table = 'details_service';

    protected $primaryKey = 'publication_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publication_id',
        'metier',
        'tarif_indicatif',
        'unite_tarif',
        'zones_intervention',
        'disponibilite',
        'telephone_contact',
        'afficher_telephone',
        'whatsapp',
        'date_souhaitee',
        'budget',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarif_indicatif' => 'integer',
            'unite_tarif' => UniteTarif::class,
            'zones_intervention' => 'array',
            'telephone_contact' => TelephoneE164::class,
            'afficher_telephone' => 'boolean',
            'whatsapp' => TelephoneE164::class,
            'date_souhaitee' => 'date',
            'budget' => 'integer',
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
