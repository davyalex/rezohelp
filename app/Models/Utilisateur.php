<?php

namespace App\Models;

use App\Casts\TelephoneE164;
use App\Enums\ConfianceUtilisateur;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\UtilisateurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte membre. Identifiant : téléphone (E.164) + mot de passe.
 * Le téléphone n'est jamais exposé publiquement.
 */
class Utilisateur extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UtilisateurFactory> */
    use HasFactory, HasRoles, HorodatageFrancais, InteractsWithMedia, Notifiable, SoftDeletes;

    protected $table = 'utilisateurs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenoms',
        'pays_code',
        'telephone',
        'email',
        'mot_de_passe',
        'commune_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'mot_de_passe',
        'remember_token',
        'telephone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telephone' => TelephoneE164::class.':pays_code',
            'mot_de_passe' => 'hashed',
            'confiance' => ConfianceUtilisateur::class,
            'telephone_verifie_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'suspendu_le' => 'datetime',
            'suspendu_jusqu_au' => 'datetime',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    /**
     * Nom affiché publiquement (prénoms + initiale du nom).
     */
    public function nomAffiche(): string
    {
        $prenoms = trim((string) $this->prenoms);

        return $prenoms === '' ? $this->nom : $prenoms.' '.mb_substr($this->nom, 0, 1).'.';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
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
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
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
        return $this->hasMany(Publication::class, 'auteur_id');
    }

    /**
     * @return HasMany<Entreprise, $this>
     */
    public function entreprises(): HasMany
    {
        return $this->hasMany(Entreprise::class, 'proprietaire_id');
    }

    /**
     * @return HasMany<Alerte, $this>
     */
    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
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
     * Publications enregistrées (table enregistrements).
     *
     * @return BelongsToMany<Publication, $this>
     */
    public function publicationsEnregistrees(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'enregistrements')->withPivot('cree_le');
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

    /**
     * @return HasMany<Commande, $this>
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
