<?php

namespace App\Notifications;

use App\Models\Alerte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Récapitulatif quotidien (7h00) d'une alerte quotidienne.
 */
class RecapitulatifAlertes extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $titres  titres des premières publications
     */
    public function __construct(
        public readonly Alerte $alerte,
        public readonly int $nombre,
        public readonly array $titres,
        public readonly string $lien,
    ) {}

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
            'titre' => ($this->nombre === 1 ? '1 nouvelle publication' : $this->nombre.' nouvelles publications').' pour « '.$this->alerte->nom.' »',
            'message' => implode(' · ', $this->titres),
            'alerte_id' => $this->alerte->id,
            'lien' => $this->lien,
        ];
    }
}
