<?php

namespace App\Actions\Veille;

use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Events\PublicationPubliee;
use App\Models\Publication;
use App\Services\CorrespondanceVeille;
use App\Support\UrlSource;

/**
 * Réexamine les annonces de la veille restées en attente après un changement des sources
 * (nouveau domaine déclaré, confiance relevée, publication automatique cochée) :
 *
 * - l'annonce est rattachée à la source qui correspond désormais à son domaine ;
 * - elle est mise en ligne si DeciderPublicationVeille l'autorise, avec le score déjà calculé.
 *
 * Ne sont jamais reprises : les annonces déjà passées en ligne puis remises en attente
 * (signalements, score en baisse), celles sans score (l'IA ne les a pas encore notées)
 * et celles déjà expirées.
 */
class ReevaluerAnnoncesVeille
{
    public function __construct(
        private readonly CorrespondanceVeille $correspondance,
        private readonly DeciderPublicationVeille $decider,
    ) {}

    /**
     * @return array{examinees: int, rattachees: int, publiees: int}
     */
    public function executer(): array
    {
        $bilan = ['examinees' => 0, 'rattachees' => 0, 'publiees' => 0];

        Publication::query()
            ->where('source', SourcePublication::VeilleIa->value)
            ->where('statut', StatutPublication::EnAttente->value)
            ->whereNull('publiee_le')
            ->whereNotNull('score_fiabilite')
            ->where(fn ($requete) => $requete->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->chunkById(200, function ($publications) use (&$bilan): void {
                foreach ($publications as $publication) {
                    $bilan['examinees']++;

                    if ($this->rattacher($publication)) {
                        $bilan['rattachees']++;
                    }

                    $enLigne = $this->decider->peutPublier($publication);

                    if ($enLigne) {
                        $this->decider->mettreEnLigne($publication);
                        $bilan['publiees']++;
                    }

                    $publication->save();

                    if ($enLigne) {
                        PublicationPubliee::dispatch($publication);
                    }
                }
            });

        return $bilan;
    }

    /**
     * Rattache l'annonce à la source déclarée pour son domaine, si elle a changé.
     */
    private function rattacher(Publication $publication): bool
    {
        $url = UrlSource::normaliser((string) $publication->source_url);

        if ($url === null) {
            return false;
        }

        $source = $this->correspondance->source($url);

        if ($source->id === $publication->source_veille_id) {
            return false;
        }

        $publication->source_veille_id = $source->id;

        // Le nom affiché suit la source déclarée ; « Inconnue » garde le nom donné par la veille.
        if ($source->domaine !== null) {
            $publication->source_nom = $source->nom;
        }

        return true;
    }
}
