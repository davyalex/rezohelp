<?php

namespace App\Services\Veille;

use App\Contracts\SourceVeille;
use App\Donnees\FichierVeille;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Source de veille locale : lit les fichiers CSV d'un dossier
 * (par défaut tests/Fixtures/veille).
 */
class FakeSourceVeille implements SourceVeille
{
    public function __construct(private readonly string $dossier) {}

    public function listerFichiers(): array
    {
        $chemins = glob($this->chemin('*.csv')) ?: [];
        sort($chemins);

        return array_map(fn (string $chemin): FichierVeille => new FichierVeille(
            id: 'fake-'.basename($chemin, '.csv'),
            nom: basename($chemin),
            modifieLe: CarbonImmutable::createFromTimestamp((int) filemtime($chemin)),
        ), $chemins);
    }

    public function contenuCsv(FichierVeille $fichier): string
    {
        $chemin = $this->chemin(basename($fichier->nom));

        if (! is_file($chemin)) {
            throw new RuntimeException("Fichier de veille introuvable : {$fichier->nom}");
        }

        return (string) file_get_contents($chemin);
    }

    private function chemin(string $nom): string
    {
        return rtrim($this->dossier, '/\\').DIRECTORY_SEPARATOR.$nom;
    }
}
