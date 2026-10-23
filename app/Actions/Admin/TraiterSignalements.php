<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Actions\Publication\MettreAJourConfiance;
use App\Enums\StatutPublication;
use App\Models\Administrateur;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * D — Décision sur les signalements d'une publication (CONTEXTE §13.3.D) :
 *  - retablir : signalements non retenus ; une publication masquée automatiquement revient en ligne ;
 *  - supprimer : signalements retenus, publication supprimée ;
 *  - supprimer_et_suspendre : idem, et l'auteur est suspendu.
 * Tous les signalements ouverts sont marqués traités ; la confiance de l'auteur est recalculée.
 */
class TraiterSignalements
{
    use JournaliseActivite;

    public const array DECISIONS = ['retablir', 'supprimer', 'supprimer_et_suspendre'];

    public function __construct(
        private readonly GererUtilisateur $utilisateurs,
        private readonly MettreAJourConfiance $mettreAJourConfiance,
    ) {}

    public function executer(Publication $publication, Administrateur $admin, string $decision): int
    {
        $retenu = $decision !== 'retablir';

        $traites = DB::transaction(function () use ($publication, $admin, $decision, $retenu): int {
            $traites = Signalement::query()
                ->where('publication_id', $publication->id)
                ->whereNull('traite_le')
                ->update(['traite_le' => now(), 'traite_par' => $admin->id, 'retenu' => $retenu]);

            // Masquée par les signalements (déjà publiée une fois) : elle retrouve le fil.
            if (! $retenu && $publication->statut === StatutPublication::EnAttente && $publication->publiee_le !== null) {
                $publication->statut = StatutPublication::Publiee;
                $publication->save();
            }

            if ($retenu) {
                $publication->delete();
            }

            $this->journaliser($admin, 'signalements.traites', $publication, ['decision' => $decision, 'signalements' => $traites]);

            return $traites;
        });

        $auteur = $publication->auteur_id !== null ? Utilisateur::query()->find($publication->auteur_id) : null;

        if ($auteur !== null && $retenu) {
            $this->mettreAJourConfiance->executer($auteur);

            if ($decision === 'supprimer_et_suspendre' && ! $auteur->is($admin) && ! $auteur->estSuspendu()) {
                $this->utilisateurs->suspendre($auteur, $admin, 'Publication signalée et supprimée : « '.$publication->titre.' ».');
            }
        }

        return $traites;
    }
}
