<?php

namespace App\Jobs;

use App\Actions\Veille\LancerImportVeille;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * « Importer maintenant » du back-office : la liste des fichiers Drive est lue en file,
 * jamais pendant la requête HTTP. Un seul lancement à la fois.
 */
class LancerImportVeilleEnFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(LancerImportVeille $lancer): void
    {
        $lancer->executer();
    }
}
