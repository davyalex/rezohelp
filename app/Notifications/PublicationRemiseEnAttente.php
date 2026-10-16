<?php

namespace App\Notifications;

use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'auteur que sa publication a été retirée du fil pour vérification.
 */
class PublicationRemiseEnAttente extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Publication $publication) {}

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
            'titre' => 'Publication en cours de vérification',
            'message' => '« '.$this->publication->titre.' » a été retirée du fil le temps d’une vérification par notre équipe.',
            'publication_id' => $this->publication->id,
            'lien' => route('espace.publications.index'),
        ];
    }
}
