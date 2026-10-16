<?php

namespace App\Actions\Auth;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Enums\ObjetOtp;
use App\Models\Utilisateur;

/**
 * Envoie un code de réinitialisation si le numéro correspond à un compte.
 * Rien ne permet à l'appelant de savoir si le compte existe.
 */
class DemanderReinitialisationMotDePasse
{
    public function __construct(private readonly EnvoyerCodeOtp $envoyerCodeOtp) {}

    public function executer(string $telephone): void
    {
        $utilisateur = Utilisateur::query()->where('telephone', $telephone)->first();

        if ($utilisateur !== null) {
            $this->envoyerCodeOtp->executer($telephone, ObjetOtp::Reinitialisation, $utilisateur);
        }
    }
}
