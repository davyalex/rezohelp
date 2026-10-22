<?php

namespace App\Services\Veille;

use App\Contracts\SourceVeille;
use App\Donnees\FichierVeille;
use Carbon\CarbonImmutable;
use Google\Client as ClientGoogle;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\Client\Factory;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Dossier Google Drive « Offres Emploi Abidjan » (CONTEXTE §12), lu avec un compte de service
 * (le dossier est partagé avec son adresse e-mail) : Google Sheets exportés en CSV, fichiers CSV
 * téléchargés tels quels.
 *
 * Le client Google passe par le client HTTP de Laravel : les délais et Http::fake() s'appliquent.
 */
class GoogleDriveSourceVeille implements SourceVeille
{
    private const string TABLEUR = 'application/vnd.google-apps.spreadsheet';

    private const int PAR_PAGE = 100;

    private ?Drive $drive = null;

    /**
     * @param  array{fichier_identifiants: ?string, dossier_id: ?string}  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {}

    public function listerFichiers(): array
    {
        $dossier = $this->config['dossier_id'] ?? '';

        if ($dossier === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $dossier)) {
            throw new RuntimeException('Identifiant du dossier Drive à fournir (GOOGLE_DRIVE_DOSSIER_ID).');
        }

        $fichiers = [];
        $page = null;

        do {
            $liste = $this->drive()->files->listFiles(array_filter([
                'q' => "'{$dossier}' in parents and trashed = false and (mimeType = '".self::TABLEUR."' or mimeType = 'text/csv')",
                'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime, webViewLink)',
                'orderBy' => 'createdTime',
                'pageSize' => self::PAR_PAGE,
                'pageToken' => $page,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ], fn ($valeur): bool => $valeur !== null));

            foreach ($liste->getFiles() as $fichier) {
                $fichiers[] = $this->fichier($fichier);
            }

            $page = $liste->getNextPageToken();
        } while ($page !== null && $page !== '');

        return $fichiers;
    }

    public function contenuCsv(FichierVeille $fichier): string
    {
        // Un CSV déposé tel quel garde son extension ; un Google Sheet n'en a pas et s'exporte.
        $reponse = str_ends_with(strtolower($fichier->nom), '.csv')
            ? $this->drive()->files->get($fichier->id, ['alt' => 'media', 'supportsAllDrives' => true])
            : $this->drive()->files->export($fichier->id, 'text/csv', ['alt' => 'media']);

        if (! $reponse instanceof ResponseInterface) {
            throw new RuntimeException("Contenu illisible pour le fichier Drive {$fichier->nom}.");
        }

        return (string) $reponse->getBody();
    }

    private function fichier(DriveFile $fichier): FichierVeille
    {
        return new FichierVeille(
            id: (string) $fichier->getId(),
            // L'extension distingue un CSV d'un Google Sheet pour le téléchargement.
            nom: (string) $fichier->getName().($fichier->getMimeType() === 'text/csv' && ! str_ends_with(strtolower((string) $fichier->getName()), '.csv') ? '.csv' : ''),
            modifieLe: $fichier->getModifiedTime() !== '' ? CarbonImmutable::parse($fichier->getModifiedTime()) : null,
            url: $fichier->getWebViewLink(),
        );
    }

    private function drive(): Drive
    {
        if ($this->drive !== null) {
            return $this->drive;
        }

        $identifiants = (string) ($this->config['fichier_identifiants'] ?? '');
        $chemin = $identifiants !== '' && ! str_starts_with($identifiants, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $identifiants)
            ? base_path($identifiants)
            : $identifiants;

        if ($chemin === '' || ! is_file($chemin)) {
            throw new RuntimeException('Fichier d’identifiants du compte de service Google à fournir (GOOGLE_DRIVE_FICHIER_IDENTIFIANTS).');
        }

        $client = new ClientGoogle;
        $client->setAuthConfig($chemin);
        $client->addScope(Drive::DRIVE_READONLY);
        $client->setHttpClient($this->http->createPendingRequest()->timeout(60)->buildClient());

        return $this->drive = new Drive($client);
    }
}
