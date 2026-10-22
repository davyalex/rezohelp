<?php

namespace App\Jobs;

use App\Actions\Veille\ImporterLigneVeille;
use App\Contracts\SourceVeille;
use App\Donnees\FichierVeille;
use App\Enums\StatutImportVeille;
use App\Enums\StatutLigneVeille;
use App\Models\ImportVeille;
use App\Models\Publication;
use App\Support\LecteurCsvVeille;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Importe un fichier de la veille (CONTEXTE §12) : chaque ligne passe par ImporterLigneVeille,
 * les compteurs de imports_veille sont mis à jour, puis chaque annonce importée est enrichie
 * par l'IA en file. Rejouer un import est sans risque : les doublons sont écartés par l'empreinte.
 */
class ImporterFichierVeille implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $importId) {}

    public function handle(SourceVeille $source, ImporterLigneVeille $importerLigne): void
    {
        $import = ImportVeille::query()->find($this->importId);

        if ($import === null) {
            return;
        }

        $contenu = $source->contenuCsv(new FichierVeille($import->fichier_drive_id, $import->nom_fichier));
        $lignes = LecteurCsvVeille::lire($contenu);

        // Nouvelle tentative : le journal des lignes est reconstruit.
        $import->lignes()->delete();
        $compteurs = [StatutLigneVeille::Importee->value => 0, StatutLigneVeille::Doublon->value => 0, StatutLigneVeille::Rejetee->value => 0];
        $aEnrichir = [];

        foreach ($lignes as ['numero' => $numero, 'valeurs' => $valeurs]) {
            $resultat = $importerLigne->executer($import, $numero, $valeurs);
            $compteurs[$resultat->statut->value]++;

            if ($resultat->statut === StatutLigneVeille::Importee && $resultat->publication_id !== null) {
                $aEnrichir[] = $resultat->publication_id;
            }
        }

        $import->forceFill([
            'statut' => StatutImportVeille::Termine,
            'nb_lignes' => count($lignes),
            'nb_importees' => $compteurs[StatutLigneVeille::Importee->value],
            'nb_doublons' => $compteurs[StatutLigneVeille::Doublon->value],
            'nb_rejetees' => $compteurs[StatutLigneVeille::Rejetee->value],
            'erreur' => null,
            'traite_le' => now(),
        ])->save();

        // Une annonce déjà notée par l'IA (import rejoué) n'est pas renvoyée à l'IA.
        Publication::query()->whereKey($aEnrichir)->whereNull('score_fiabilite')->pluck('id')
            ->each(fn (int $publicationId) => EnrichirPublicationIA::dispatch($publicationId));
    }

    public function failed(?Throwable $erreur): void
    {
        ImportVeille::query()->whereKey($this->importId)->update([
            'statut' => StatutImportVeille::Erreur->value,
            'erreur' => mb_substr((string) $erreur?->getMessage(), 0, 2000),
            'traite_le' => now(),
        ]);
    }
}
