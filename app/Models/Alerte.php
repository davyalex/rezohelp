<?php

namespace App\Models;

use App\Enums\FrequenceAlerte;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\AlerteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Alerte d'un membre : critères de correspondance avec les nouvelles publications.
 */
class Alerte extends Model
{
    /** @use HasFactory<AlerteFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'alertes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'type',
        'categorie_id',
        'commune_id',
        'type_contrat',
        'mots_cles',
        'frequence',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePublication::class,
            'type_contrat' => TypeContrat::class,
            'frequence' => FrequenceAlerte::class,
            'active' => 'boolean',
            'derniere_notification_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Utilisateur, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /**
     * @return BelongsTo<Categorie, $this>
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Publications déjà rapprochées de l'alerte (anti-doublon).
     *
     * @return BelongsToMany<Publication, $this>
     */
    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'alertes_publications')->withPivot('notifie_le');
    }
}
