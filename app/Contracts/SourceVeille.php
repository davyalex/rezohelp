<?php

namespace App\Contracts;

use App\Donnees\FichierVeille;

/**
 * Source des fichiers de la veille IA (drivers : google_drive, fake).
 */
interface SourceVeille
{
    /**
     * @return list<FichierVeille>
     */
    public function listerFichiers(): array;

    /**
     * Contenu CSV (UTF-8) d'un fichier de veille.
     */
    public function contenuCsv(FichierVeille $fichier): string;
}
