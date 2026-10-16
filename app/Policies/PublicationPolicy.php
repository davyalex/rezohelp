<?php

namespace App\Policies;

use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Models\Utilisateur;

class PublicationPolicy
{
    /**
     * Page publique : publiée, ou clôturée / expirée (avec un bandeau).
     * Une publication non publiée n'est visible que par son auteur et la modération.
     */
    public function view(?Utilisateur $utilisateur, Publication $publication): bool
    {
        if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::Cloturee, StatutPublication::Expiree], true)) {
            return true;
        }

        return $utilisateur !== null
            && ($utilisateur->id === $publication->auteur_id || $utilisateur->can('publications.moderer'));
    }

    /**
     * J'aime, commentaire, enregistrement, signalement : publication en ligne, compte actif.
     */
    public function interagir(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $publication->statut === StatutPublication::Publiee && ! $utilisateur->estSuspendu();
    }
}
