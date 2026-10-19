<?php

namespace App\Notifications;

use App\Models\Alerte;
use App\Models\Publication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Une nouvelle publication correspond à une alerte instantanée.
 * Canal database ; le canal SMS (alertes.canal_sms) s'ajoute au lot 6.
 */
class AlerteCorrespondante extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Publication $publication, public readonly Alerte $alerte) {}

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
            'titre' => 'Alerte « '.$this->alerte->nom.' »',
            'message' => $this->publication->titre,
            'publication_id' => $this->publication->id,
            'alerte_id' => $this->alerte->id,
            'lien' => route('publications.show', $this->publication->slug),
        ];
    }
}
