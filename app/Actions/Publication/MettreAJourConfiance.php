<?php

namespace App\Actions\Publication;

use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifRejet;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;

/**
 * Niveau de confiance d'un compte (CONTEXTE §11.6) :
 *  - restreint : rejets_arnaque_pour_restreint rejets pour arnaque, ou un signalement retenu ;
 *  - fiable : publications_pour_fiable publications validées et aucun signalement retenu ;
 *  - sinon nouveau.
 * Un compte restreint ne redevient jamais fiable tout seul : seul l'admin lève la restriction
 * (restriction_levee_le : les rejets et signalements antérieurs ne comptent plus).
 */
class MettreAJourConfiance
{
    public function executer(Utilisateur $utilisateur): void
    {
        if ($utilisateur->confiance === ConfianceUtilisateur::Restreint) {
            return;
        }

        $publications = Publication::withTrashed()->where('auteur_id', $utilisateur->id);
        // Après une levée de restriction par l'admin, seuls les faits postérieurs comptent.
        $depuis = $utilisateur->restriction_levee_le;

        $rejetsArnaque = (clone $publications)
            ->where('statut', StatutPublication::Rejetee->value)
            ->where('motif_rejet', MotifRejet::ArnaqueSuspectee->value)
            ->when($depuis !== null, fn ($requete) => $requete->where('modifie_le', '>', $depuis))
            ->count();

        $signalementRetenu = Signalement::query()
            ->where('retenu', true)
            ->when($depuis !== null, fn ($requete) => $requete->where('traite_le', '>', $depuis))
            ->whereIn('publication_id', (clone $publications)->select('id'))
            ->exists();

        $validees = (clone $publications)->whereNotNull('publiee_le')->count();

        $confiance = match (true) {
            $signalementRetenu || $rejetsArnaque >= Parametre::entier('rejets_arnaque_pour_restreint') => ConfianceUtilisateur::Restreint,
            $validees >= Parametre::entier('publications_pour_fiable') => ConfianceUtilisateur::Fiable,
            default => ConfianceUtilisateur::Nouveau,
        };

        if ($confiance !== $utilisateur->confiance) {
            $utilisateur->forceFill(['confiance' => $confiance])->save();
        }
    }
}
