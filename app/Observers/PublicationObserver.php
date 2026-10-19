<?php

namespace App\Observers;

use App\Actions\Commande\BasculerMisesEnAvant;
use App\Actions\Publication\MettreAJourConfiance;
use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\CacheFil;

/**
 * - Invalidation du cache du fil (CONTEXTE §8) : seul endroit où fil:version change
 *   pour une publication. Les compteurs (J'aime, vues…) sont modifiés sans événement.
 * - Niveau de confiance de l'auteur (§11.6) quand une publication est validée ou rejetée.
 * - Mises en avant suspendues quand la publication sort du fil, reprises à son retour (§14.9).
 */
class PublicationObserver
{
    public function __construct(
        private readonly CacheFil $cache,
        private readonly MettreAJourConfiance $mettreAJourConfiance,
        private readonly BasculerMisesEnAvant $misesEnAvant,
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

        if (! $publication->wasChanged('statut')) {
            return;
        }

        if ($avant === StatutPublication::Publiee) {
            $this->misesEnAvant->suspendre($publication);
        } elseif ($publication->statut === StatutPublication::Publiee) {
            $this->misesEnAvant->reprendre($publication);
        }

        if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::Rejetee], true) && $publication->auteur_id !== null) {
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
            $this->misesEnAvant->suspendre($publication);
        }
    }

    public function restored(Publication $publication): void
    {
        if ($publication->statut === StatutPublication::Publiee) {
            $this->cache->invaliderFil();
            $this->misesEnAvant->reprendre($publication);
        }
    }
}
