<?php

namespace App\Notifications;

use App\Models\Commande;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Paiement confirmé : le reçu est disponible.
 */
class CommandePayee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commande $commande) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Paiement confirmé',
            'message' => 'Commande '.$this->commande->numero.' — '.Montant::fcfa($this->commande->montant_ttc_fcfa).'. Votre reçu est disponible.',
            'commande_id' => $this->commande->id,
            'lien' => route('espace.recu', $this->commande->id),
        ];
    }
}
