<?php

namespace App\Observers;

use App\Jobs\RafraichirBadges;
use App\Models\Abonnement;
use App\Services\CacheFil;

/**
 * Un abonnement change : les droits du titulaire sont recalculés (§14.5)
 * et le badge « Pro » / « Entreprise » de ses cartes est mis à jour.
 */
class AbonnementObserver
{
    public function __construct(private readonly CacheFil $cache) {}

    public function saved(Abonnement $abonnement): void
    {
        $this->rafraichir($abonnement);
    }

    public function deleted(Abonnement $abonnement): void
    {
        $this->rafraichir($abonnement);
    }

    private function rafraichir(Abonnement $abonnement): void
    {
        $this->cache->oublierDroits($abonnement->titulaire_type, (int) $abonnement->titulaire_id);
        RafraichirBadges::dispatch($abonnement->titulaire_type, (int) $abonnement->titulaire_id);
    }
}
