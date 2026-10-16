<?php

namespace App\Actions\Otp;

use App\Enums\ObjetOtp;
use App\Enums\ResultatOtp;
use App\Models\CodeOtp;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie un code OTP : 5 essais au maximum, puis le code est bloqué.
 * Un code valide n'est utilisable qu'une fois.
 */
class VerifierCodeOtp
{
    public const int ESSAIS_MAX = 5;

    public function executer(string $telephone, ObjetOtp $objet, string $code): ResultatOtp
    {
        return DB::transaction(function () use ($telephone, $objet, $code): ResultatOtp {
            $codeOtp = CodeOtp::query()
                ->enCours($telephone, $objet)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($codeOtp === null) {
                return ResultatOtp::Expire;
            }

            if ($codeOtp->essais >= self::ESSAIS_MAX) {
                return ResultatOtp::Bloque;
            }

            if ($codeOtp->expire_le->isPast()) {
                return ResultatOtp::Expire;
            }

            $codeOtp->increment('essais');

            if (! hash_equals($codeOtp->empreinte_code, EnvoyerCodeOtp::empreinte(trim($code)))) {
                return $codeOtp->essais >= self::ESSAIS_MAX ? ResultatOtp::Bloque : ResultatOtp::Invalide;
            }

            $codeOtp->forceFill(['utilise_le' => now()])->save();

            return ResultatOtp::Valide;
        });
    }
}
