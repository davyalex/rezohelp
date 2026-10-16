<?php

namespace App\Notifications;

use App\Enums\TypeReponse;
use App\Models\Reponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Nouvelle candidature, inscription, proposition ou demande de contact pour l'auteur.
 */
class NouvelleReponse extends Notification implements ShouldQueue
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
            'titre' => match ($this->reponse->type) {
                TypeReponse::Candidature => 'Nouvelle candidature',
                TypeReponse::Inscription => 'Nouvelle inscription',
                TypeReponse::Proposition => 'Un prestataire est disponible',
                TypeReponse::Contact => 'Nouvelle demande de contact',
            },
            'message' => 'Pour « '.$publication->titre.' ».',
            'publication_id' => $publication->id,
            'lien' => route('espace.reponses.index', $publication->id),
        ];
    }
}
