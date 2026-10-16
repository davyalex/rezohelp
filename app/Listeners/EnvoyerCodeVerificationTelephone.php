<?php

namespace App\Listeners;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Enums\ObjetOtp;
use App\Models\Utilisateur;
use Illuminate\Auth\Events\Registered;

/**
 * Envoie le code de vérification du numéro juste après l'inscription.
 */
class EnvoyerCodeVerificationTelephone
{
    public function __construct(private readonly EnvoyerCodeOtp $envoyerCodeOtp) {}

    public function handle(Registered $evenement): void
    {
        $utilisateur = $evenement->user;

        if ($utilisateur instanceof Utilisateur && $utilisateur->telephone !== null && $utilisateur->telephone_verifie_le === null) {
            $this->envoyerCodeOtp->executer($utilisateur->telephone, ObjetOtp::Verification, $utilisateur);
        }
    }
}
