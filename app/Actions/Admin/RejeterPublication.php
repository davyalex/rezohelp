<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\MotifRejet;
use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Exceptions\OperationImpossibleException;
use App\Models\Administrateur;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationRejetee;
use Illuminate\Support\Facades\DB;

/**
 * Rejet par un modérateur (CONTEXTE §13.3.B) : motif prédéfini (+ texte libre), notification
 * à l'auteur d'une publication native. La confiance de l'auteur est recalculée par l'observer.
 */
class RejeterPublication
{
    use JournaliseActivite;

    public function executer(Publication $publication, Administrateur $moderateur, MotifRejet $motif, ?string $detail = null, string $action = 'publication.rejetee'): void
    {
        if ($publication->statut !== StatutPublication::EnAttente) {
            throw new OperationImpossibleException('Seule une publication en attente peut être refusée.');
        }

        $detail = $detail !== null && trim($detail) !== '' ? trim($detail) : null;

        DB::transaction(function () use ($publication, $moderateur, $motif, $detail, $action): void {
            $publication->statut = StatutPublication::Rejetee;
            $publication->motif_rejet = $motif;
            $publication->motif_rejet_detail = $detail;
            $publication->save();

            $this->journaliser($moderateur, $action, $publication, ['motif' => $motif->value, 'detail' => $detail]);
        });

        if ($publication->source === SourcePublication::Utilisateur && $publication->auteur_id !== null) {
            Utilisateur::query()->find($publication->auteur_id)?->notify(new PublicationRejetee($publication));
        }
    }
}
