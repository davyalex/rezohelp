<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\SourcePublication;
use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypeProduit;
use App\Events\PublicationPubliee;
use App\Exceptions\OperationImpossibleException;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationValidee;
use App\Services\Offres;
use Illuminate\Support\Facades\DB;

/**
 * Validation par un modérateur (CONTEXTE §13.3.B) : décision humaine, seule autre porte
 * vers le statut « publiee » que DeciderStatutPublication (D-054). Déclenche les alertes,
 * notifie l'auteur et journalise.
 */
class ValiderPublication
{
    use JournaliseActivite;

    public function __construct(private readonly Offres $offres) {}

    public function executer(Publication $publication, Utilisateur $moderateur): void
    {
        if ($publication->statut !== StatutPublication::EnAttente) {
            throw new OperationImpossibleException('Seule une publication en attente peut être validée.');
        }

        DB::transaction(function () use ($publication, $moderateur): void {
            $premiereMiseEnLigne = $publication->publiee_le === null;

            $publication->statut = StatutPublication::Publiee;
            $publication->motif_rejet = null;
            $publication->motif_rejet_detail = null;

            // Une première mise en ligne entre en tête du fil ; une remise en ligne garde sa place.
            if ($premiereMiseEnLigne) {
                $publication->publiee_le = now();
                $publication->tri_le = now();
            }

            $publication->save();

            $this->journaliser($moderateur, 'publication.validee', $publication, ['premiere_mise_en_ligne' => $premiereMiseEnLigne]);
        });

        PublicationPubliee::dispatch($publication);

        if ($publication->source === SourcePublication::Utilisateur && $publication->auteur_id !== null) {
            Utilisateur::query()->find($publication->auteur_id)?->notify(new PublicationValidee($publication, $this->remontee($publication)));
        }
    }

    /**
     * Incitation « Remontez votre annonce » pour une annonce d'artisan (§14.11).
     */
    private function remontee(Publication $publication): ?Produit
    {
        return $publication->sous_type === SousTypeService::Offre ? $this->offres->produit(TypeProduit::Remontee) : null;
    }
}
