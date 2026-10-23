<?php

namespace App\Models;

use App\Models\Concerns\HorodatageCreation;
use Database\Factories\JournalActiviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal d'audit des actions d'administration (§13.1).
 */
class JournalActivite extends Model
{
    /** @use HasFactory<JournalActiviteFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'journal_activites';

    /**
     * @var list<string>
     */
    protected $fillable = ['administrateur_id', 'action', 'sujet_type', 'sujet_id', 'donnees', 'ip'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'donnees' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Administrateur, $this>
     */
    public function administrateur(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }
}
