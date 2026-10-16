<?php

namespace App\Notifications;

use App\Enums\StatutReponse;
use App\Models\Reponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Informe le répondant que l'auteur a vu, retenu ou refusé sa réponse.
 */
class StatutReponseModifie extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reponse $reponse) {}

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
        $publication = $this->reponse->publication;

        return [
            'titre' => match ($this->reponse->statut) {
                StatutReponse::Vue => 'Votre réponse a été vue',
                StatutReponse::Retenue => 'Bonne nouvelle : votre réponse est retenue',
                StatutReponse::Refusee => 'Votre réponse n’a pas été retenue',
                StatutReponse::Envoyee => 'Votre réponse a été envoyée',
            },
            'message' => 'Pour « '.$publication->titre.' ».',
            'publication_id' => $publication->id,
            'lien' => route('publications.show', $publication->slug),
        ];
    }
}
