<?php

namespace App\Models;

use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Models\Concerns\HorodatageFrancais;
use App\Observers\AbonnementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Database\Factories\AbonnementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Souscription d'un titulaire (utilisateur ou entreprise) à un plan, pour une période.
 */
#[ObservedBy(AbonnementObserver::class)]
class Abonnement extends Model
{
    /** @use HasFactory<AbonnementFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'abonnements';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulaire_type',
        'titulaire_id',
        'plan_id',
        'statut',
        'origine',
        'debut_le',
        'fin_le',
        'commande_id',
        'motif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutAbonnement::class,
            'origine' => OrigineAbonnement::class,
            'debut_le' => 'datetime',
            'fin_le' => 'datetime',
            'relances_envoyees' => 'array',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
