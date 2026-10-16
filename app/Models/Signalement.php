<?php

namespace App\Models;

use App\Enums\MotifSignalement;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signalement extends Model
{
    /** @use HasFactory<SignalementFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'signalements';

    /**
     * @var list<string>
     */
    protected $fillable = ['publication_id', 'utilisateur_id', 'motif', 'commentaire'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'motif' => MotifSignalement::class,
            'retenu' => 'boolean',
            'traite_le' => 'datetime',
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
     * @return BelongsTo<Utilisateur, $this>
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'traite_par');
    }
}
