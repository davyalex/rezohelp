<?php

namespace App\Models;

use App\Enums\StatutCommentaire;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\CommentaireFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commentaire extends Model
{
    /** @use HasFactory<CommentaireFactory> */
    use HasFactory, HorodatageFrancais, SoftDeletes;

    protected $table = 'commentaires';

    /**
     * @var list<string>
     */
    protected $fillable = ['publication_id', 'utilisateur_id', 'parent_id', 'contenu'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutCommentaire::class,
        ];
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * @return BelongsTo<Utilisateur, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /**
     * @return BelongsTo<Commentaire, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Commentaire::class, 'parent_id');
    }

    /**
     * @return HasMany<Commentaire, $this>
     */
    public function sousCommentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class, 'parent_id');
    }
}
