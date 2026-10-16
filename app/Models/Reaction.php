<?php

namespace App\Models;

use App\Enums\TypeReaction;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\ReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    /** @use HasFactory<ReactionFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'reactions';

    /**
     * @var list<string>
     */
    protected $fillable = ['publication_id', 'utilisateur_id', 'type'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeReaction::class,
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
}
