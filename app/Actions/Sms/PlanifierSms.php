<?php

namespace App\Actions\Sms;

use App\Contracts\FournisseurSms;
use App\Enums\MotifSms;
use App\Enums\StatutSms;
use App\Jobs\EnvoyerSms;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;

/**
 * Journalise un SMS dans sms_envoyes puis le confie à la file d'attente.
 *
 * Le texte réellement envoyé voyage dans le job (chiffré) ; le journal peut
 * en garder une version masquée (codes OTP).
 */
class PlanifierSms
{
    public function __construct(private readonly FournisseurSms $fournisseur) {}

    public function executer(
        string $telephone,
        string $message,
        MotifSms $motif,
        ?Utilisateur $utilisateur = null,
        ?string $messageJournal = null,
    ): SmsEnvoye {
        $sms = new SmsEnvoye([
            'utilisateur_id' => $utilisateur?->id,
            'telephone' => $telephone,
            'message' => $messageJournal ?? $message,
            'motif' => $motif,
            'fournisseur' => $this->fournisseur->nom(),
        ]);
        $sms->statut = StatutSms::EnFile;
        $sms->save();

        EnvoyerSms::dispatch($sms->id, $message);

        return $sms;
    }
}
