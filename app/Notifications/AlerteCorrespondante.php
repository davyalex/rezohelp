<?php

namespace App\Notifications;

use App\Enums\MotifSms;
use App\Models\Alerte;
use App\Models\Publication;
use App\Notifications\Canaux\MessageSms;
use App\Notifications\Canaux\SmsChannel;
use App\Services\Referentiels;
use App\Support\TexteSms;
use Illuminate\Notifications\Notification;

/**
 * Une nouvelle publication correspond à une alerte instantanée.
 * Toujours dans « Mon espace » ; aussi par SMS quand le membre y a droit
 * (alertes.canal_sms, numéro vérifié, plafond du jour non atteint — décidé par CorrespondreAlertes).
 *
 * Envoyée directement depuis le job CorrespondreAlertes (déjà en file) : le SMS est journalisé
 * tout de suite dans sms_envoyes, ce qui rend le plafond quotidien exact ; l'envoi réel reste en file.
 */
class AlerteCorrespondante extends Notification
{
    public function __construct(
        public readonly Publication $publication,
        public readonly Alerte $alerte,
        public readonly bool $parSms = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->parSms ? ['database', SmsChannel::class] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Alerte « '.$this->alerte->nom.' »',
            'message' => $this->publication->titre,
            'publication_id' => $this->publication->id,
            'alerte_id' => $this->alerte->id,
            'lien' => route('publications.show', $this->publication->slug),
        ];
    }

    /**
     * « Rezohelp: Electricien dispo, Cocody. Voir: rezohelp.ci/p/ab12cd » (≤ 160 caractères, GSM-7).
     */
    public function toSms(object $notifiable): MessageSms
    {
        return new MessageSms(
            TexteSms::alerte(
                $this->publication->titre,
                app(Referentiels::class)->nomCommune($this->publication->commune_id),
                route('publications.court', $this->publication->code_court),
            ),
            MotifSms::Alerte,
        );
    }
}
