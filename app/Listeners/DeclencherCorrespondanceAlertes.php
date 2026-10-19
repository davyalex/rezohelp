<?php

namespace App\Listeners;

use App\Events\PublicationPubliee;
use App\Jobs\CorrespondreAlertes;

/**
 * Une publication entre dans le fil : la recherche des alertes correspondantes part en file.
 */
class DeclencherCorrespondanceAlertes
{
    public function handle(PublicationPubliee $evenement): void
    {
        CorrespondreAlertes::dispatch($evenement->publication->id);
    }
}
