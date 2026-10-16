<?php

namespace App\Models;

use App\Models\Concerns\HorodatageFrancais;
use App\Observers\ReferentielObserver;
use Database\Factories\PaysFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pays ouvert à l'inscription (sélecteur d'indicatif).
 */
#[ObservedBy(ReferentielObserver::class)]
class Pays extends Model
{
    /** @use HasFactory<PaysFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'pays';

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'nom', 'indicatif', 'actif', 'ordre'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * @return HasMany<Commune, $this>
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class, 'pays_code', 'code');
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopeActifs(Builder $requete): void
    {
        $requete->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
