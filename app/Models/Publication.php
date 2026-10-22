<?php

namespace App\Models;

use App\Enums\MotifRejet;
use App\Enums\SourcePublication;
use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Concerns\HorodatageFrancais;
use App\Observers\PublicationObserver;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Élément du fil : offre d'emploi, service (offre ou demande) ou formation.
 * Les colonnes lues par les cartes du fil sont dénormalisées ici.
 */
#[ObservedBy(PublicationObserver::class)]
class Publication extends Model implements HasMedia
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory, HorodatageFrancais, InteractsWithMedia, SoftDeletes;

    protected $table = 'publications';

    /**
     * Le statut, les compteurs, le score et les dates de modération ne sont
     * jamais remplis en masse : ils passent par les Actions dédiées.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'sous_type',
        'titre',
        'slug',
        'contenu',
        'extrait',
        'categorie_id',
        'commune_id',
        'auteur_id',
        'entreprise_id',
        'auteur_nom_affiche',
        'auteur_avatar_url',
        'auteur_verifie',
        'auteur_badge',
        'faits_cles',
        'type_contrat',
        'image_url',
        'image_largeur',
        'image_hauteur',
        'source',
        'source_nom',
        'source_url',
        'note_veille',
        'source_empreinte',
        'source_veille_id',
        'import_veille_id',
        'expire_le',
        'code_court',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePublication::class,
            'sous_type' => SousTypeService::class,
            'statut' => StatutPublication::class,
            'source' => SourcePublication::class,
            'motif_rejet' => MotifRejet::class,
            'signaux_risque' => 'array',
            'faits_cles' => 'array',
            'type_contrat' => TypeContrat::class,
            'auteur_verifie' => 'boolean',
            'image_largeur' => 'integer',
            'image_hauteur' => 'integer',
            'score_fiabilite' => 'integer',
            'nb_jaime' => 'integer',
            'nb_commentaires' => 'integer',
            'nb_partages' => 'integer',
            'nb_vues' => 'integer',
            'nb_reponses' => 'integer',
            'nb_prolongations' => 'integer',
            'publiee_le' => 'datetime',
            'expire_le' => 'datetime',
            'tri_le' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('fil')
            ->format('webp')
            ->fit(Fit::Max, 640, 640);

        $this->addMediaConversion('miniature')
            ->format('webp')
            ->fit(Fit::Crop, 96, 96);
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
     * @return BelongsTo<Utilisateur, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'auteur_id');
    }

    /**
     * @return BelongsTo<Entreprise, $this>
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * @return BelongsTo<SourceVeille, $this>
     */
    public function sourceVeille(): BelongsTo
    {
        return $this->belongsTo(SourceVeille::class);
    }

    /**
     * @return BelongsTo<ImportVeille, $this>
     */
    public function importVeille(): BelongsTo
    {
        return $this->belongsTo(ImportVeille::class);
    }

    /**
     * @return HasOne<DetailEmploi, $this>
     */
    public function detailEmploi(): HasOne
    {
        return $this->hasOne(DetailEmploi::class);
    }

    /**
     * @return HasOne<DetailService, $this>
     */
    public function detailService(): HasOne
    {
        return $this->hasOne(DetailService::class);
    }

    /**
     * @return HasOne<DetailFormation, $this>
     */
    public function detailFormation(): HasOne
    {
        return $this->hasOne(DetailFormation::class);
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * @return HasMany<Commentaire, $this>
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class);
    }

    /**
     * @return HasMany<Reponse, $this>
     */
    public function reponses(): HasMany
    {
        return $this->hasMany(Reponse::class);
    }

    /**
     * @return HasMany<Signalement, $this>
     */
    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class);
    }

    /**
     * @return HasMany<MiseEnAvant, $this>
     */
    public function misesEnAvant(): HasMany
    {
        return $this->hasMany(MiseEnAvant::class);
    }

    /**
     * Membres ayant enregistré la publication.
     *
     * @return BelongsToMany<Utilisateur, $this>
     */
    public function enregistreePar(): BelongsToMany
    {
        return $this->belongsToMany(Utilisateur::class, 'enregistrements')->withPivot('cree_le');
    }

    /**
     * Alertes déjà notifiées pour cette publication.
     *
     * @return BelongsToMany<Alerte, $this>
     */
    public function alertes(): BelongsToMany
    {
        return $this->belongsToMany(Alerte::class, 'alertes_publications')->withPivot('notifie_le');
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopePubliees(Builder $requete): void
    {
        $requete->where('statut', StatutPublication::Publiee->value);
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
    public function scopeDansCommune(Builder $requete, int $communeId): void
    {
        $requete->where('commune_id', $communeId);
    }

    public function estPubliee(): bool
    {
        return $this->statut === StatutPublication::Publiee;
    }
}
