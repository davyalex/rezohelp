<?php

namespace App\Console\Commands;

use App\Actions\Veille\LancerImportVeille;
use Illuminate\Console\Command;

/**
 * Import de la veille (CONTEXTE §12), planifié à 01h20, 09h20 et 17h20 : nouveaux fichiers du
 * dossier Drive « Offres Emploi Abidjan », un job ImporterFichierVeille par fichier.
 */
class ImporterVeille extends Command
{
    protected $signature = 'veille:importer';

    protected $description = 'Importe les nouveaux fichiers de la veille IA déposés dans Google Drive';

    public function handle(LancerImportVeille $lancer): int
    {
        $imports = $lancer->executer();

        $this->info(count($imports) === 0
            ? 'Aucun nouveau fichier.'
            : count($imports).' fichier(s) mis en file : '.implode(', ', array_map(fn ($import): string => $import->nom_fichier, $imports)).'.');

        return self::SUCCESS;
    }
}
