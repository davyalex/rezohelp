<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\StatutImportVeille;
use App\Jobs\ImporterFichierVeille;
use App\Jobs\LancerImportVeilleEnFile;
use App\Models\ImportVeille;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * E — Boutons « Importer maintenant » et « Relancer » de la veille (CONTEXTE §13.3.E) :
 * le travail part en file, la page répond tout de suite.
 */
class GererImportVeille
{
    use JournaliseActivite;

    public function importerMaintenant(Utilisateur $admin): void
    {
        LancerImportVeilleEnFile::dispatch();
        $this->journaliser($admin, 'veille.import_lance');
    }

    /**
     * Rejoue un fichier (après une erreur, ou une source réactivée) : les annonces qu'il a déjà
     * créées restent comptées comme importées, celles d'autres fichiers comme doublons.
     */
    public function relancer(ImportVeille $import, Utilisateur $admin): void
    {
        DB::transaction(function () use ($import, $admin): void {
            $import->forceFill([
                'statut' => StatutImportVeille::EnCours,
                'nb_lignes' => 0, 'nb_importees' => 0, 'nb_doublons' => 0, 'nb_rejetees' => 0,
                'erreur' => null,
            ])->save();

            $this->journaliser($admin, 'veille.import_relance', $import, ['fichier' => $import->nom_fichier]);
        });

        ImporterFichierVeille::dispatch($import->id);
    }
}
