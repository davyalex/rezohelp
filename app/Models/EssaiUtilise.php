<?php

namespace App\Models;

use Database\Factories\EssaiUtiliseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Essai gratuit consommé : empreinte SHA-256 du numéro + famille de plans (§14.6).
 * Survit à la suppression du compte. Table à clé composée : insertion et lecture seulement.
 */
class EssaiUtilise extends Model
{
    /** @use HasFactory<EssaiUtiliseFactory> */
    use HasFactory;

    protected $table = 'essais_utilises';

    protected $primaryKey = 'empreinte_telephone';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['empreinte_telephone', 'famille', 'utilise_le'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'utilise_le' => 'datetime',
        ];
    }
}
