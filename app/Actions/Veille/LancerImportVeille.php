<?php

namespace App\Actions\Veille;

use App\Contracts\SourceVeille;
use App\Enums\StatutImportVeille;
use App\Jobs\ImporterFichierVeille;
use App\Models\ImportVeille;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Cherche les nouveaux fichiers de la veille (CONTEXTE §12) : un fichier déjà présent dans
 * imports_veille est ignoré ; chaque nouveau fichier reçoit sa ligne d'import et son job.
 */
class LancerImportVeille
{
    public function __construct(private readonly SourceVeille $source) {}

    /**
     * @return list<ImportVeille> imports créés
     */
    public function executer(): array
    {
        $connus = ImportVeille::query()->pluck('fichier_drive_id')->flip();
        $crees = [];

        foreach ($this->source->listerFichiers() as $fichier) {
            if ($connus->has($fichier->id)) {
                continue;
            }

            try {
                $import = new ImportVeille(['fichier_drive_id' => $fichier->id, 'nom_fichier' => $fichier->nom]);
                $import->statut = StatutImportVeille::EnCours;
                $import->save();
            } catch (UniqueConstraintViolationException) {
                // Lancé deux fois en même temps : l'autre lancement s'occupe de ce fichier.
                continue;
            }

            ImporterFichierVeille::dispatch($import->id);
            $crees[] = $import;
        }

        return $crees;
    }
}
