<?php

namespace App\Actions\Otp;

use App\Actions\Sms\PlanifierSms;
use App\Enums\MotifSms;
use App\Enums\ObjetOtp;
use App\Models\CodeOtp;
use App\Models\Utilisateur;

/**
 * Génère un code OTP à 6 chiffres, valable 10 minutes, et l'envoie par SMS.
 * Les codes précédents du même usage pour ce numéro sont annulés.
 */
class EnvoyerCodeOtp
{
    public const int LONGUEUR = 6;

    public const int VALIDITE_MINUTES = 10;

    public function __construct(private readonly PlanifierSms $planifierSms) {}

    public function executer(string $telephone, ObjetOtp $objet, ?Utilisateur $utilisateur = null): CodeOtp
    {
        CodeOtp::query()->enCours($telephone, $objet)->update(['utilise_le' => now()]);

        $code = str_pad((string) random_int(0, 10 ** self::LONGUEUR - 1), self::LONGUEUR, '0', STR_PAD_LEFT);

        $codeOtp = CodeOtp::query()->create([
            'telephone' => $telephone,
            'objet' => $objet,
            'empreinte_code' => self::empreinte($code),
            'expire_le' => now()->addMinutes(self::VALIDITE_MINUTES),
        ]);

        $this->planifierSms->executer(
            telephone: $telephone,
            message: $this->message($code),
            motif: MotifSms::Otp,
            utilisateur: $utilisateur,
            messageJournal: $this->message(str_repeat('*', self::LONGUEUR)),
        );

        return $codeOtp;
    }

    /**
     * Empreinte HMAC du code : un code divulgué par une copie de la base reste inutilisable.
     */
    public static function empreinte(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Message GSM-7, sans accents, en un seul SMS.
     */
    private function message(string $code): string
    {
        return 'Resohelph: votre code est '.$code.'. Il expire dans '.self::VALIDITE_MINUTES.' min. Ne le partagez avec personne.';
    }
}
