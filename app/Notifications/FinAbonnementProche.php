<?php

namespace App\Notifications;

use App\Enums\MotifSms;
use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Notifications\Canaux\MessageSms;
use App\Notifications\Canaux\SmsChannel;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Relance J-3, J-1 et J0 avant la fin d'un essai ou d'un pass (CONTEXTE §14.6) :
 * dans « Mon espace » et par SMS si le numéro est vérifié. Aucun débit automatique.
 */
class FinAbonnementProche extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Abonnement $abonnement, public readonly int $joursRestants) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => $this->titre(),
            'message' => $this->incitation(),
            'abonnement_id' => $this->abonnement->id,
            'lien' => route('espace.abonnement'),
        ];
    }

    public function toSms(object $notifiable): MessageSms
    {
        return new MessageSms('Resohelph: '.$this->titre().' '.$this->incitation(), MotifSms::Relance);
    }

    private function titre(): string
    {
        $nom = $this->abonnement->statut === StatutAbonnement::Essai ? 'Votre essai '.$this->abonnement->plan->nom : 'Votre formule '.$this->abonnement->plan->nom;

        return match (true) {
            $this->joursRestants <= 0 => $nom.' se termine aujourd’hui.',
            $this->joursRestants === 1 => $nom.' se termine demain.',
            default => $nom.' se termine dans '.$this->joursRestants.' jours.',
        };
    }

    private function incitation(): string
    {
        $plan = $this->abonnement->plan;

        return 'Continuer pour '.Montant::fcfa($plan->prix_fcfa).' / '.$plan->duree_jours.' jours.';
    }
}
