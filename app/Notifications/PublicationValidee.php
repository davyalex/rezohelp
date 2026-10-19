<?php

namespace App\Notifications;

use App\Models\Produit;
use App\Models\Publication;
use App\Support\Montant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Publication validée par la modération (CONTEXTE §11.7). Pour une annonce d'artisan,
 * propose la remontée payante quand le produit est actif (§14.11).
 */
class PublicationValidee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Publication $publication, public readonly ?Produit $remontee = null) {}

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
        $message = '« '.$this->publication->titre.' » est en ligne.';

        if ($this->remontee !== null) {
            $message .= ' Remontez votre annonce en tête du fil pour '.Montant::fcfa($this->remontee->prix_fcfa).' depuis « Mes publications ».';
        }

        return [
            'titre' => 'Publication validée',
            'message' => $message,
            'publication_id' => $this->publication->id,
            'lien' => $this->remontee !== null ? route('espace.publications.index') : route('publications.show', $this->publication->slug),
        ];
    }
}
