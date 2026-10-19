<?php

namespace App\Actions\Compte;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Actions\Otp\VerifierCodeOtp;
use App\Enums\ObjetOtp;
use App\Enums\ResultatOtp;
use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Illuminate\Validation\ValidationException;

/**
 * Changement de numéro (identifiant du compte) : un code est envoyé au nouveau numéro,
 * qui n'est enregistré qu'une fois le code saisi.
 */
class ChangerNumero
{
    public function __construct(
        private readonly EnvoyerCodeOtp $envoyerCodeOtp,
        private readonly VerifierCodeOtp $verifierCodeOtp,
        private readonly TelephoneService $telephones,
    ) {}

    public function demander(Utilisateur $utilisateur, string $nouveauNumero): void
    {
        $this->envoyerCodeOtp->executer($nouveauNumero, ObjetOtp::ChangementNumero, $utilisateur);
    }

    public function confirmer(Utilisateur $utilisateur, string $nouveauNumero, string $code): ResultatOtp
    {
        $resultat = $this->verifierCodeOtp->executer($nouveauNumero, ObjetOtp::ChangementNumero, $code);

        if ($resultat !== ResultatOtp::Valide) {
            return $resultat;
        }

        // Le numéro a pu être pris entre la demande et la confirmation.
        if (Utilisateur::withTrashed()->where('telephone', $nouveauNumero)->whereKeyNot($utilisateur->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Ce numéro de téléphone est déjà utilisé par un compte.']);
        }

        $utilisateur->forceFill([
            'pays_code' => $this->telephones->paysDe($nouveauNumero) ?? $utilisateur->pays_code,
            'telephone' => $nouveauNumero,
            'telephone_verifie_le' => now(),
        ])->save();

        return ResultatOtp::Valide;
    }
}
