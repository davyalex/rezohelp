<?php

namespace App\Models;

use App\Models\Concerns\HorodatageFrancais;
use App\Observers\ReferentielObserver;
use Database\Factories\CommuneFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ReferentielObserver::class)]
class Commune extends Model
{
    /** @use HasFactory<CommuneFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'communes';

    /**
     * @var list<string>
     */
    protected $fillable = ['pays_code', 'ville', 'nom', 'slug', 'actif', 'ordre'];

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
     * @return BelongsTo<Pays, $this>
     */
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_code', 'code');
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopeActives(Builder $requete): void
    {
        $requete->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
