<?php

namespace App\Jobs;

use App\Actions\Veille\ReevaluerAnnoncesVeille;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Réévaluation des annonces de la veille en attente, lancée depuis le back-office ou après la
 * modification d'une source : en file, jamais pendant la requête HTTP. Une seule à la fois.
 */
class ReevaluerAnnoncesVeilleEnFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(ReevaluerAnnoncesVeille $reevaluer): void
    {
        $reevaluer->executer();
    }
}
