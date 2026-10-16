<?php

namespace App\Models;

use App\Casts\TelephoneE164;
use App\Enums\ObjetOtp;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\CodeOtpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Code à 6 chiffres envoyé par SMS : valable 10 minutes, 5 essais.
 */
class CodeOtp extends Model
{
    /** @use HasFactory<CodeOtpFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'codes_otp';

    /**
     * @var list<string>
     */
    protected $fillable = ['telephone', 'objet', 'empreinte_code', 'expire_le'];

    /**
     * @var list<string>
     */
    protected $hidden = ['empreinte_code'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telephone' => TelephoneE164::class,
            'objet' => ObjetOtp::class,
            'essais' => 'integer',
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $requete
     */
    public function scopeEnCours(Builder $requete, string $telephone, ObjetOtp $objet): void
    {
        $requete->where('telephone', $telephone)
            ->where('objet', $objet->value)
            ->whereNull('utilise_le');
    }
}
