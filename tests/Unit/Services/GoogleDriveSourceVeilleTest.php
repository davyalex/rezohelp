<?php

use App\Donnees\FichierVeille;
use App\Services\Veille\GoogleDriveSourceVeille;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Compte de service jetable : la clé est générée pour le test, aucun appel réseau réel.
    // Sous Windows, OpenSSL a besoin du openssl.cnf livré avec PHP ; ailleurs, la configuration par défaut suffit.
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $configuration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($configuration)) {
        $options['config'] = $configuration;
    }
    $cle = openssl_pkey_new($options);
    openssl_pkey_export($cle, $clePrivee, null, $options);

    $this->identifiants = storage_path('framework/testing/compte-service-'.uniqid().'.json');
    File::ensureDirectoryExists(dirname($this->identifiants));
    file_put_contents($this->identifiants, json_encode([
        'type' => 'service_account',
        'project_id' => 'rezohelp-test',
        'private_key_id' => 'cle-1',
        'private_key' => $clePrivee,
        'client_email' => 'veille@rezohelp-test.iam.gserviceaccount.com',
        'client_id' => '123',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));

    Http::fake([
        'www.googleapis.com/drive/v3/files?*' => Http::sequence()
            ->push(['nextPageToken' => 'page-2', 'files' => [
                ['id' => 'feuille-1', 'name' => 'Veille 2026-10-16 00h51', 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'modifiedTime' => '2026-10-16T00:51:00Z', 'webViewLink' => 'https://docs.google.com/spreadsheets/d/feuille-1'],
            ]])
            ->push(['files' => [
                ['id' => 'csv-2', 'name' => 'veille-2026-10-16-0851.csv', 'mimeType' => 'text/csv', 'modifiedTime' => '2026-10-16T08:51:00Z'],
            ]]),
        'www.googleapis.com/drive/v3/files/feuille-1/export*' => Http::response("Catégorie,Intitulé\nFormation,Bootcamp\n", 200, ['Content-Type' => 'text/csv']),
        'www.googleapis.com/drive/v3/files/csv-2?*' => Http::response("Catégorie,Intitulé\nOffre d'emploi,Comptable\n", 200, ['Content-Type' => 'text/csv']),
    ]);
});

afterEach(fn () => File::delete($this->identifiants));

function drive(array $config): GoogleDriveSourceVeille
{
    return new GoogleDriveSourceVeille(app(Factory::class), $config);
}

it('liste les Google Sheets et CSV du dossier, page après page, avec le compte de service', function () {
    $fichiers = drive(['fichier_identifiants' => $this->identifiants, 'dossier_id' => 'dossier-veille'])->listerFichiers();

    expect(array_map(fn (FichierVeille $f): array => [$f->id, $f->nom], $fichiers))->toBe([
        ['feuille-1', 'Veille 2026-10-16 00h51'],
        ['csv-2', 'veille-2026-10-16-0851.csv'],
    ])->and($fichiers[0]->modifieLe?->toIso8601String())->toBe('2026-10-16T00:51:00+00:00');

    // Http::fake() répond avant le middleware d'authentification de Google : le jeton du compte de
    // service n'est pas sollicité ici (vérification manuelle avec les vrais identifiants, AVANCEMENT).
    Http::assertSent(fn (RequeteHttp $r): bool => str_contains($r->url(), 'drive/v3/files?')
        && str_contains(urldecode($r->url()), "'dossier-veille' in parents and trashed = false")
        && str_contains(urldecode($r->url()), "mimeType = 'application/vnd.google-apps.spreadsheet' or mimeType = 'text/csv'"));
    Http::assertSent(fn (RequeteHttp $r): bool => str_contains($r->url(), 'pageToken=page-2'));
});

it('exporte un Google Sheet en CSV et télécharge un CSV tel quel', function () {
    $source = drive(['fichier_identifiants' => $this->identifiants, 'dossier_id' => 'dossier-veille']);

    expect($source->contenuCsv(new FichierVeille('feuille-1', 'Veille 2026-10-16 00h51')))->toContain('Formation,Bootcamp')
        ->and($source->contenuCsv(new FichierVeille('csv-2', 'veille-2026-10-16-0851.csv')))->toContain("Offre d'emploi,Comptable");

    Http::assertSent(fn (RequeteHttp $r): bool => str_contains($r->url(), 'files/feuille-1/export') && str_contains($r->url(), 'mimeType=text%2Fcsv'));
    Http::assertSent(fn (RequeteHttp $r): bool => str_contains($r->url(), 'files/csv-2?') && str_contains($r->url(), 'alt=media'));
});

it('signale les identifiants à fournir', function (array $config, string $message) {
    expect(fn () => drive($config)->listerFichiers())->toThrow(RuntimeException::class, $message);
})->with([
    'dossier manquant' => [['fichier_identifiants' => 'introuvable.json', 'dossier_id' => null], 'GOOGLE_DRIVE_DOSSIER_ID'],
    'identifiants manquants' => [['fichier_identifiants' => 'introuvable.json', 'dossier_id' => 'dossier'], 'GOOGLE_DRIVE_FICHIER_IDENTIFIANTS'],
]);
