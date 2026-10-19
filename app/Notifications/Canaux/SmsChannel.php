<?php

namespace App\Notifications\Canaux;

use App\Actions\Sms\PlanifierSms;
use App\Models\Utilisateur;
use Illuminate\Notifications\Notification;

/**
 * Canal « sms » des notifications (CONTEXTE §14.8) : uniquement vers un numéro vérifié,
 * jamais entre 21h00 et 7h00 (l'envoi est différé au matin).
 */
class SmsChannel
{
    public function __construct(private readonly PlanifierSms $planifierSms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof Utilisateur || $notifiable->telephone === null || $notifiable->telephone_verifie_le === null) {
            return;
        }

        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof MessageSms) {
            return;
        }

        $this->planifierSms->executer(
            telephone: $notifiable->telephone,
            message: $message->texte,
            motif: $message->motif,
            utilisateur: $notifiable,
            respecterPlageSilencieuse: true,
        );
    }
}
