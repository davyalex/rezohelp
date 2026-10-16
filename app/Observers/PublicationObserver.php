<?php

namespace App\Observers;

use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Services\CacheFil;

/**
 * Invalidation du cache du fil (CONTEXTE §8) : seul endroit où fil:version change
 * pour une publication. Les compteurs (J'aime, vues…) sont modifiés sans événement
 * et n'invalident donc rien.
 */
class PublicationObserver
{
    public function __construct(private readonly CacheFil $cache) {}

    public function created(Publication $publication): void
    {
        if ($publication->statut === StatutPublication::Publiee) {
            $this->cache->invaliderFil();
        }
    }

    public function updated(Publication $publication): void
    {
        $avant = $publication->getOriginal('statut');

        if ($avant === StatutPublication::Publiee || $publication->statut === StatutPublication::Publiee) {
            $this->cache->invaliderFil();
        }
    }

    public function deleted(Publication $publication): void
    {
        if ($publication->statut === StatutPublication::Publiee) {
            $this->cache->invaliderFil();
        }
    }

    public function restored(Publication $publication): void
    {
        $this->deleted($publication);
    }
}
