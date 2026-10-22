<?php

namespace App\Actions\Veille;

use App\Enums\NiveauConfiance;
use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\SourceVeille;

/**
 * Seul endroit qui décide la mise en ligne automatique d'une annonce de la veille (CLAUDE.md,
 * CONTEXTE §12) : la source est active, de confiance **haute**, autorisée à publier sans
 * modération, **et** le score de fiabilité atteint seuil_publication_auto. Sinon l'annonce reste
 * en attente pour la modération.
 */
class DeciderPublicationVeille
{
    public function peutPublier(Publication $publication): bool
    {
        if ($publication->source !== SourcePublication::VeilleIa
            || $publication->statut !== StatutPublication::EnAttente
            // Déjà mise en ligne puis retirée (signalements, dépublication, score) : seule l'équipe la remet en ligne.
            || $publication->publiee_le !== null
            || $publication->score_fiabilite === null) {
            return false;
        }

        $source = $publication->source_veille_id !== null ? SourceVeille::query()->find($publication->source_veille_id) : null;

        return $source !== null
            && $source->actif
            && $source->publication_auto
            && $source->niveau_confiance === NiveauConfiance::Haut
            && $publication->score_fiabilite >= Parametre::entier('seuil_publication_auto');
    }

    /**
     * Passe l'annonce en ligne (sans l'enregistrer) : en tête du fil à partir de maintenant.
     */
    public function mettreEnLigne(Publication $publication): void
    {
        $publication->statut = StatutPublication::Publiee;
        $publication->publiee_le = now();
        $publication->tri_le = now();
    }
}
