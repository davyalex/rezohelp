<?php

namespace App\Models;

use App\Enums\PasserelleCommande;
use App\Enums\StatutCommande;
use App\Enums\TypeArticle;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\CommandeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Achat d'un plan ou d'un produit, avec son paiement et son reçu (§14.7).
 * Le montant est figé à la création de la commande.
 */
class Commande extends Model
{
    /** @use HasFactory<CommandeFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'commandes';

    /**
     * Statut, paiement et reçu ne sont jamais remplis en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'numero',
        'utilisateur_id',
        'titulaire_type',
        'titulaire_id',
        'article_type',
        'article_id',
        'publication_id',
        'commune_id',
        'montant_ttc_fcfa',
        'taux_tva',
        'montant_ht_fcfa',
        'passerelle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'article_type' => TypeArticle::class,
            'statut' => StatutCommande::class,
            'passerelle' => PasserelleCommande::class,
            'montant_ttc_fcfa' => 'integer',
            'montant_ht_fcfa' => 'integer',
            'taux_tva' => 'decimal:2',
            'payee_le' => 'datetime',
            'donnees_passerelle' => 'array',
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
        return $this->belongsTo(Plan::class, 'article_id');
    }

    /**
     * @return BelongsTo<Produit, $this>
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'article_id');
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return HasOne<Abonnement, $this>
     */
    public function abonnement(): HasOne
    {
        return $this->hasOne(Abonnement::class);
    }
}
