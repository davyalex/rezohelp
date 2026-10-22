<?php

namespace App\Models;

use App\Enums\StatutImportVeille;
use App\Models\Concerns\HorodatageFrancais;
use Database\Factories\ImportVeilleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un fichier de veille importé depuis Google Drive (§12).
 */
class ImportVeille extends Model
{
    /** @use HasFactory<ImportVeilleFactory> */
    use HasFactory, HorodatageFrancais;

    protected $table = 'imports_veille';

    /**
     * @var list<string>
     */
    protected $fillable = ['fichier_drive_id', 'nom_fichier'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutImportVeille::class,
            'nb_lignes' => 'integer',
            'nb_importees' => 'integer',
            'nb_doublons' => 'integer',
            'nb_rejetees' => 'integer',
            'traite_le' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * @return HasMany<LigneImportVeille, $this>
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneImportVeille::class, 'import_veille_id');
    }

    /**
     * @return HasMany<AppelIa, $this>
     */
    public function appelsIa(): HasMany
    {
        return $this->hasMany(AppelIa::class);
    }
}
