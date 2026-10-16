<?php

namespace App\Actions\Publication;

use App\Enums\ConfianceUtilisateur;
use App\Enums\StatutPublication;
use App\Exceptions\PublicationImpossibleException;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Seul endroit qui décide si une publication native part en ligne (CONTEXTE §11.6).
 *
 * | Situation                                                     | Décision            |
 * | Auteur restreint                                              | impossible          |
 * | Première publication du compte                                | en_attente          |
 * | Au moins un signal de risque                                  | en_attente          |
 * | Auteur fiable et score ≥ seuil (sans score : fiable suffit)   | publiee             |
 * | Entreprise vérifiée et aucun signal                           | publiee             |
 * | Tous les autres cas                                           | en_attente          |
 */
class DeciderStatutPublication
{
    /**
     * @param  list<string>  $signaux
     *
     * @throws PublicationImpossibleException
     */
    public function executer(
        Utilisateur $auteur,
        ?Entreprise $entreprise,
        array $signaux,
        ?int $score = null,
        ?int $publicationIgnoreeId = null,
    ): StatutPublication {
        if ($auteur->confiance === ConfianceUtilisateur::Restreint) {
            throw new PublicationImpossibleException('Votre compte ne peut plus publier. Contactez l’équipe Resohelph.');
        }

        if ($this->estPremierePublication($auteur, $publicationIgnoreeId)) {
            return StatutPublication::EnAttente;
        }

        if ($signaux !== []) {
            return StatutPublication::EnAttente;
        }

        if ($auteur->confiance === ConfianceUtilisateur::Fiable
            && ($score === null || $score >= Parametre::entier('seuil_publication_auto'))) {
            return StatutPublication::Publiee;
        }

        if ($entreprise !== null && $entreprise->verifiee) {
            return StatutPublication::Publiee;
        }

        return StatutPublication::EnAttente;
    }

    /**
     * Aucune autre publication soumise (les brouillons ne comptent pas, supprimées comprises).
     */
    private function estPremierePublication(Utilisateur $auteur, ?int $publicationIgnoreeId): bool
    {
        return ! Publication::withTrashed()
            ->where('auteur_id', $auteur->id)
            ->where('statut', '!=', StatutPublication::Brouillon->value)
            ->when($publicationIgnoreeId !== null, fn ($requete) => $requete->whereKeyNot($publicationIgnoreeId))
            ->exists();
    }
}
