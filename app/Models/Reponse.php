<?php

namespace App\Models;

use App\Enums\StatutReponse;
use App\Enums\TypeReponse;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\ReponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Candidature, inscription, proposition ou demande de contact (§11.8).
 */
class Reponse extends Model
{
    /** @use HasFactory<ReponseFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'reponses';

    /**
     * @var list<string>
     */
    protected $fillable = ['publication_id', 'utilisateur_id', 'type', 'message', 'telephone_partage'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeReponse::class,
            'statut' => StatutReponse::class,
            'telephone_partage' => 'boolean',
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
