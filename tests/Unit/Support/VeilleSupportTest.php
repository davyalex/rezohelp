<?php

use App\Support\LecteurCsvVeille;
use App\Support\UrlSource;

it('donne la même empreinte malgré les paramètres de suivi, le slash final, www et http', function (string $variante) {
    $reference = UrlSource::normaliser('https://www.emploi.ci/offre/comptable-12345');

    expect(UrlSource::normaliser($variante))->toBe($reference)
        ->and($reference)->toBe('https://emploi.ci/offre/comptable-12345');
})->with([
    'suivi utm' => 'https://www.emploi.ci/offre/comptable-12345?utm_source=veille&utm_medium=sheet',
    'slash final' => 'https://www.emploi.ci/offre/comptable-12345/',
    'sans www' => 'https://emploi.ci/offre/comptable-12345',
    'http' => 'http://www.emploi.ci/offre/comptable-12345',
    'fbclid et fragment' => 'https://WWW.Emploi.ci/offre/comptable-12345?fbclid=abc#postuler',
]);

it('garde les paramètres qui désignent l’annonce, triés', function () {
    expect(UrlSource::normaliser('https://site.ci/annonce?ref_src=x&page=2&id=42'))->toBe('https://site.ci/annonce?id=42&page=2')
        ->and(UrlSource::normaliser('https://site.ci/annonce?id=43'))->not->toBe(UrlSource::normaliser('https://site.ci/annonce?id=42'));
});

it('refuse un lien qui n’est pas une adresse web', function (string $lien) {
    expect(UrlSource::normaliser($lien))->toBeNull();
})->with(['javascript:alert(1)', 'mailto:rh@site.ci', 'pas une url', 'ftp://site.ci/fichier']);

it('lit le CSV de la veille avec un BOM, le séparateur « ; » et une cellule sur plusieurs lignes', function () {
    $csv = "\xEF\xBB\xBFCategorie;Intitule;Entreprise/Organisme;Lieu;Contrat/Duree/Prix;Date publication;Domaine;Source;Lien;Resume\r\n"
        ."Offre d'emploi;Comptable;SID;Plateau;CDI;2026-10-15;Finance;Emploi.ci;https://emploi.ci/1;\"Ligne 1\nLigne 2\"\r\n"
        ."\r\n"
        ."Formation;Bootcamp;ODC;Marcory;3 mois - gratuit;2026-10-14;Informatique;ODC;https://odc.ci/2;Gratuit\r\n";

    $lignes = LecteurCsvVeille::lire($csv);

    expect($lignes)->toHaveCount(2)
        ->and($lignes[0]['numero'])->toBe(2)
        ->and($lignes[0]['valeurs'])->toMatchArray(['categorie' => "Offre d'emploi", 'entreprise' => 'SID', 'conditions' => 'CDI', 'resume' => "Ligne 1\nLigne 2"])
        ->and($lignes[1]['valeurs']['lien'])->toBe('https://odc.ci/2');
});

it('lit le fichier de démonstration de la veille', function () {
    $lignes = LecteurCsvVeille::lire((string) file_get_contents(base_path('tests/Fixtures/veille/veille-2026-10-16-0051.csv')));

    expect($lignes)->toHaveCount(3)
        ->and(array_column(array_column($lignes, 'valeurs'), 'categorie'))->toBe(["Offre d'emploi", 'Home service / petits métiers', 'Formation']);
});
