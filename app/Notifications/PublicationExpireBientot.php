<?php

namespace App\Notifications;

use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * La publication expire dans 3 jours (CONTEXTE §11.7) : l'auteur peut la prolonger.
 */
class PublicationExpireBientot extends Notification implements ShouldQueue
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
            'titre' => 'Publication bientôt expirée',
            'message' => '« '.$this->publication->titre.' » ne sera plus visible après le '.$this->publication->expire_le?->translatedFormat('j F').'. Vous pouvez la prolonger depuis « Mes publications ».',
            'publication_id' => $this->publication->id,
            'lien' => route('espace.publications.index'),
        ];
    }
}
