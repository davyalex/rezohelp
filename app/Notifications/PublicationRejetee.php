<?php

namespace App\Notifications;

use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Publication refusée par la modération, avec le motif (CONTEXTE §11.7) : l'auteur peut
 * la corriger et la renvoyer depuis « Mes publications ».
 */
class PublicationRejetee extends Notification implements ShouldQueue
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
        $motif = $this->publication->motif_rejet?->libelle() ?? 'motif non précisé';
        $detail = $this->publication->motif_rejet_detail !== null ? ' — '.$this->publication->motif_rejet_detail : '';

        return [
            'titre' => 'Publication refusée',
            'message' => '« '.$this->publication->titre.' » n’a pas été acceptée : '.$motif.$detail.'. Vous pouvez la corriger et la renvoyer.',
            'publication_id' => $this->publication->id,
            'lien' => route('espace.publications.index'),
        ];
    }
}
