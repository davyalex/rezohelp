<?php

namespace App\Actions\Sms;

use App\Contracts\FournisseurSms;
use App\Enums\MotifSms;
use App\Enums\StatutSms;
use App\Jobs\EnvoyerSms;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;
use App\Support\TexteSms;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Journalise un SMS dans sms_envoyes puis le confie à la file d'attente.
 *
 * - Le texte est ramené à l'alphabet GSM-7 et à 160 caractères (§14.8).
 * - Avec la plage silencieuse, rien ne part entre 21h00 et 7h00 : l'envoi est différé à 7h00.
 *   Les codes OTP, demandés par le membre lui-même, n'y sont pas soumis (D-022).
 * - Le texte réellement envoyé voyage dans le job (chiffré) ; le journal peut en garder
 *   une version masquée (codes OTP).
 */
class PlanifierSms
{
    public const int DEBUT_PLAGE_SILENCIEUSE = 21;

    public const int FIN_PLAGE_SILENCIEUSE = 7;

    public function __construct(private readonly FournisseurSms $fournisseur) {}

    public function executer(
        string $telephone,
        string $message,
        MotifSms $motif,
        ?Utilisateur $utilisateur = null,
        ?string $messageJournal = null,
        bool $respecterPlageSilencieuse = false,
    ): SmsEnvoye {
        $texte = TexteSms::preparer($message);

        $sms = new SmsEnvoye([
            'utilisateur_id' => $utilisateur?->id,
            'telephone' => $telephone,
            'message' => $messageJournal !== null ? TexteSms::preparer($messageJournal) : $texte,
            'motif' => $motif,
            'fournisseur' => $this->fournisseur->nom(),
        ]);
        $sms->statut = StatutSms::EnFile;
        $sms->save();

        $envoi = EnvoyerSms::dispatch($sms->id, $texte);

        if ($respecterPlageSilencieuse && ($reprise = self::finPlageSilencieuse(now())) !== null) {
            $envoi->delay($reprise);
        }

        return $sms;
    }

    /**
     * Heure de reprise si $instant tombe dans la plage silencieuse, sinon null.
     */
    public static function finPlageSilencieuse(CarbonInterface $instant): ?Carbon
    {
        $heure = (int) $instant->format('G');

        if ($heure >= self::DEBUT_PLAGE_SILENCIEUSE) {
            return Carbon::instance($instant)->addDay()->setTime(self::FIN_PLAGE_SILENCIEUSE, 0);
        }

        if ($heure < self::FIN_PLAGE_SILENCIEUSE) {
            return Carbon::instance($instant)->setTime(self::FIN_PLAGE_SILENCIEUSE, 0);
        }

        return null;
    }
}
