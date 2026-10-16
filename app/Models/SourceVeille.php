<?php

namespace App\Models;

use App\Enums\NiveauConfiance;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\SourceVeilleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site d'origine des offres de la veille (Emploi.ci…), avec son niveau de confiance.
 */
class SourceVeille extends Model
{
    /** @use HasFactory<SourceVeilleFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'sources_veille';

    /**
     * @var list<string>
     */
    protected $fillable = ['nom', 'domaine', 'niveau_confiance', 'publication_auto', 'actif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'niveau_confiance' => NiveauConfiance::class,
            'publication_auto' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
