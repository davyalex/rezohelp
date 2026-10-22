<?php

namespace App\Models;

use App\Enums\StatutLigneVeille;
use App\Models\Concerns\HorodatageCreation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne d'un fichier de la veille et son sort (importée, doublon, rejetée).
 */
class LigneImportVeille extends Model
{
    use HorodatageCreation;

    protected $table = 'lignes_import_veille';

    /**
     * @var list<string>
     */
    protected $fillable = ['import_veille_id', 'numero', 'statut', 'intitule', 'lien', 'publication_id', 'raison'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutLigneVeille::class,
            'numero' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportVeille, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ImportVeille::class, 'import_veille_id');
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class)->withTrashed();
    }
}
