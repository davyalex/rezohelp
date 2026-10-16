<?php

namespace App\Observers;

use App\Actions\Publication\MettreAJourConfiance;
use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\CacheFil;

/**
 * - Invalidation du cache du fil (CONTEXTE §8) : seul endroit où fil:version change
 *   pour une publication. Les compteurs (J'aime, vues…) sont modifiés sans événement.
 * - Niveau de confiance de l'auteur (§11.6) quand une publication est validée ou rejetée.
 */
class PublicationObserver
{
    public function __construct(
        private readonly CacheFil $cache,
        private readonly MettreAJourConfiance $mettreAJourConfiance,
    ) {}

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

        if ($publication->wasChanged('statut')
            && in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::Rejetee], true)
            && $publication->auteur_id !== null) {
            $auteur = Utilisateur::query()->find($publication->auteur_id);

            if ($auteur !== null) {
                $this->mettreAJourConfiance->executer($auteur);
            }
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
