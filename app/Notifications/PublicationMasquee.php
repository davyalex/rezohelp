<?php

namespace App\Notifications;

use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Prévient les modérateurs qu'une publication a été masquée après plusieurs signalements.
 */
class PublicationMasquee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Publication $publication, public readonly int $nbSignalements) {}

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
            'titre' => 'Publication masquée après signalements',
            'message' => '« '.$this->publication->titre.' » a reçu '.$this->nbSignalements.' signalements pour arnaque et attend une décision.',
            'publication_id' => $this->publication->id,
        ];
    }
}
