<?php

namespace App\Jobs;

use App\Contracts\FournisseurSms;
use App\Enums\StatutSms;
use App\Models\SmsEnvoye;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoie un SMS journalisé via le fournisseur configuré (SMS_DRIVER).
 * Le contenu est chiffré dans la file : il peut contenir un code OTP.
 */
class EnvoyerSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $smsId,
        public readonly string $message,
    ) {}

    public function handle(FournisseurSms $fournisseur): void
    {
        $sms = SmsEnvoye::query()->find($this->smsId);

        if ($sms === null || $sms->statut === StatutSms::Envoye) {
            return;
        }

        $resultat = $fournisseur->envoyer($sms->telephone, $this->message);

        $sms->forceFill([
            'statut' => $resultat->envoye ? StatutSms::Envoye : StatutSms::Echoue,
            'reference' => $resultat->reference,
            'cout_fcfa' => $resultat->coutFcfa,
            'envoye_le' => $resultat->envoye ? now() : null,
        ])->save();
    }
}
