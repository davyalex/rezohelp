<?php

namespace App\Models;

use App\Casts\TelephoneE164;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\EntrepriseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Entreprise ou organisme de formation : titulaire de droits (§14.2).
 */
class Entreprise extends Model implements HasMedia
{
    /** @use HasFactory<EntrepriseFactory> */
    use HasFactory, HorodatageFrancais, InteractsWithMedia, SoftDeletes;

    protected $table = 'entreprises';

    /**
     * @var list<string>
     */
    protected $fillable = ['nom', 'slug', 'secteur', 'site_web', 'telephone', 'commune_id', 'proprietaire_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telephone' => TelephoneE164::class,
            'verifiee' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Crop, 96, 96);
    }

    /**
     * @return BelongsTo<Utilisateur, $this>
     */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'proprietaire_id');
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * @return MorphMany<Abonnement, $this>
     */
    public function abonnements(): MorphMany
    {
        return $this->morphMany(Abonnement::class, 'titulaire');
    }

    /**
     * @return MorphMany<Credit, $this>
     */
    public function credits(): MorphMany
    {
        return $this->morphMany(Credit::class, 'titulaire');
    }
}
