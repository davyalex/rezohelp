<?php

use App\Donnees\FichierVeille;
use App\Services\Veille\FakeSourceVeille;

it('liste les fichiers CSV du dossier de fixtures', function () {
    $fichiers = (new FakeSourceVeille(base_path('tests/Fixtures/veille')))->listerFichiers();

    expect($fichiers)->not->toBeEmpty()
        ->and($fichiers[0])->toBeInstanceOf(FichierVeille::class)
        ->and($fichiers[0]->id)->toStartWith('fake-')
        ->and($fichiers[0]->nom)->toEndWith('.csv');
});

it('renvoie le contenu CSV d’un fichier avec les colonnes de la veille', function () {
    $source = new FakeSourceVeille(base_path('tests/Fixtures/veille'));
    $fichier = $source->listerFichiers()[0];

    $premiereLigne = strtok($source->contenuCsv($fichier), "\n");

    expect($premiereLigne)->toBe('Catégorie,Intitulé,Entreprise/Organisme,Lieu,Contrat/Durée/Prix,Date publication,Domaine,Source,Lien,Résumé');
});

it('refuse un fichier absent du dossier', function () {
    (new FakeSourceVeille(base_path('tests/Fixtures/veille')))
        ->contenuCsv(new FichierVeille('fake-absent', '../../.env'));
})->throws(RuntimeException::class);

it('renvoie une liste vide pour un dossier sans fichier', function () {
    expect((new FakeSourceVeille(sys_get_temp_dir().'/resohelph-dossier-inexistant'))->listerFichiers())->toBe([]);
});
