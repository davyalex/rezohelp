<?php

namespace App\Models;

use App\Enums\CibleOffre;
use App\Models\Concerns\HorodatageFrancais;
use App\Observers\PlanObserver;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formule d'abonnement : prix, durée, essai, cible et limites (§14.2).
 * Chaque cible a un plan par défaut gratuit (est_defaut), unique en base.
 */
#[ObservedBy(PlanObserver::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'plans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'nom',
        'description',
        'cible',
        'famille',
        'prix_fcfa',
        'duree_jours',
        'duree_essai_jours',
        'est_defaut',
        'actif',
        'visible',
        'ordre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cible' => CibleOffre::class,
            'prix_fcfa' => 'integer',
            'duree_jours' => 'integer',
            'duree_essai_jours' => 'integer',
            'est_defaut' => 'boolean',
            'actif' => 'boolean',
            'visible' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * @return HasMany<PlanLimite, $this>
     */
    public function limites(): HasMany
    {
        return $this->hasMany(PlanLimite::class);
    }

    /**
     * @return HasMany<Abonnement, $this>
     */
    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopeParDefaut(Builder $requete): void
    {
        $requete->where('est_defaut', true);
    }
}
