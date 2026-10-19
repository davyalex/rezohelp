<?php

namespace App\Policies;

use App\Enums\ConfianceUtilisateur;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Auth\Access\Response;

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
     * Publier (CONTEXTE §11.1, §11.5) : compte actif, non restreint, limite du jour non atteinte.
     * Le message explique la raison du refus au membre.
     */
    public function create(Utilisateur $utilisateur): Response
    {
        if ($utilisateur->estSuspendu()) {
            return Response::deny('Votre compte est suspendu : vous ne pouvez pas publier pour le moment.');
        }

        if ($utilisateur->confiance === ConfianceUtilisateur::Restreint) {
            return Response::deny('Votre compte ne peut plus publier. Contactez l’équipe Resohelph pour en savoir plus.');
        }

        $limite = Parametre::entier('max_publications_par_jour');
        $aujourdhui = Publication::withTrashed()
            ->where('auteur_id', $utilisateur->id)
            ->where('statut', '!=', StatutPublication::Brouillon->value)
            ->where('cree_le', '>=', now()->startOfDay())
            ->count();

        if ($aujourdhui >= $limite) {
            return Response::deny("Vous avez atteint la limite de {$limite} publications par jour. Vous pourrez publier de nouveau demain.");
        }

        return Response::allow();
    }

    /**
     * Modifier : l'auteur, tant que la publication n'est ni clôturée ni expirée (sinon : dupliquer).
     */
    public function update(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id
            && ! in_array($publication->statut, [StatutPublication::Cloturee, StatutPublication::Expiree], true)
            && ! $utilisateur->estSuspendu();
    }

    public function delete(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id;
    }

    public function cloturer(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id;
    }

    public function prolonger(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id && ! $utilisateur->estSuspendu();
    }

    public function dupliquer(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id && ! $utilisateur->estSuspendu();
    }

    /**
     * Remontée et mises en avant (§14.9) : l'auteur, compte actif ; le statut est contrôlé par l'action.
     */
    public function mettreEnAvant(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id && ! $utilisateur->estSuspendu();
    }

    /**
     * Voir et traiter les réponses reçues.
     */
    public function voirReponses(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $utilisateur->id === $publication->auteur_id;
    }

    /**
     * Répondre (§11.8) : connecté, pas l'auteur, publication en ligne.
     */
    public function repondre(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $publication->statut === StatutPublication::Publiee
            && $utilisateur->id !== $publication->auteur_id
            && ! $utilisateur->estSuspendu();
    }

    /**
     * J'aime, commentaire, enregistrement, signalement : publication en ligne, compte actif.
     */
    public function interagir(Utilisateur $utilisateur, Publication $publication): bool
    {
        return $publication->statut === StatutPublication::Publiee && ! $utilisateur->estSuspendu();
    }
}
