<?php

namespace App\Actions\Auth;

use App\Actions\Otp\VerifierCodeOtp;
use App\Enums\ObjetOtp;
use App\Enums\ResultatOtp;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mot de passe oublié : le code SMS prouve la possession du numéro.
 */
class ReinitialiserMotDePasse
{
    public function __construct(private readonly VerifierCodeOtp $verifierCodeOtp) {}

    public function executer(string $telephone, string $code, string $nouveauMotDePasse): ResultatOtp
    {
        $resultat = $this->verifierCodeOtp->executer($telephone, ObjetOtp::Reinitialisation, $code);

        if ($resultat !== ResultatOtp::Valide) {
            return $resultat;
        }

        $utilisateur = Utilisateur::query()->where('telephone', $telephone)->first();

        if ($utilisateur === null) {
            return ResultatOtp::Expire;
        }

        $utilisateur->mot_de_passe = $nouveauMotDePasse;
        $utilisateur->forceFill([
            'telephone_verifie_le' => $utilisateur->telephone_verifie_le ?? now(),
            'doit_changer_mot_de_passe' => false,
            // Déconnecte les autres appareils mémorisés (« se souvenir de moi »).
            'remember_token' => Str::random(60),
        ])->save();

        // Ferme les sessions ouvertes ailleurs avec l'ancien mot de passe.
        DB::table('sessions')->where('user_id', $utilisateur->id)->delete();

        return ResultatOtp::Valide;
    }
}
