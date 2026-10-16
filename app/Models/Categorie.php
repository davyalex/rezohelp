<?php

namespace App\Models;

use App\Enums\TypePublication;
use App\Models\Concerns\HorodatageFrancais;
use App\Observers\ReferentielObserver;
use Database\Factories\CategorieFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie (ou sous-catégorie / métier) d'un type de publication.
 */
#[ObservedBy(ReferentielObserver::class)]
class Categorie extends Model
{
    /** @use HasFactory<CategorieFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'categories';

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'nom', 'slug', 'icone', 'parent_id', 'ordre', 'actif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePublication::class,
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Categorie, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'parent_id');
    }

    /**
     * @return HasMany<Categorie, $this>
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Categorie::class, 'parent_id')->orderBy('ordre');
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
    public function scopeDuType(Builder $requete, TypePublication $type): void
    {
        $requete->where('type', $type->value);
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopeActives(Builder $requete): void
    {
        $requete->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
