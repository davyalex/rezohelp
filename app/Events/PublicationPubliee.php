<?php

namespace App\Events;

use App\Models\Publication;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une publication vient d'entrer dans le fil (publication directe ou validation en modération).
 * Déclenche la correspondance des alertes (lot 5).
 */
class PublicationPubliee implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Publication $publication) {}
}
