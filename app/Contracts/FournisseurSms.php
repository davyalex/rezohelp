<?php

namespace App\Contracts;

use App\Donnees\ResultatEnvoiSms;

/**
 * Fournisseur d'envoi de SMS (drivers : log, orange, twilio).
 */
interface FournisseurSms
{
    public function nom(): string;

    /**
     * @param  string  $telephone  numéro au format E.164
     */
    public function envoyer(string $telephone, string $message): ResultatEnvoiSms;
}
