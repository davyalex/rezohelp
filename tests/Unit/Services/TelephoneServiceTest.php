<?php

use App\Services\TelephoneService;

beforeEach(function () {
    $this->telephones = new TelephoneService;
});

it('normalise les trois formats d’un numéro ivoirien vers le même E.164', function (string $saisie) {
    expect($this->telephones->normaliser($saisie, 'CI'))->toBe('+2250708091011');
})->with([
    '0708091011',
    '07 08 09 10 11',
    '+225 07 08 09 10 11',
    '00225 07 08 09 10 11',
    '07.08.09.10.11',
]);

it('utilise la Côte d’Ivoire par défaut', function () {
    expect($this->telephones->normaliser('0708091011'))->toBe('+2250708091011');
});

it('normalise les numéros sénégalais et français', function (string $saisie, string $pays, string $attendu) {
    expect($this->telephones->normaliser($saisie, $pays))->toBe($attendu);
})->with([
    'Sénégal national' => ['77 123 45 67', 'SN', '+221771234567'],
    'Sénégal international' => ['+221 77 123 45 67', 'SN', '+221771234567'],
    'France national' => ['06 12 34 56 78', 'FR', '+33612345678'],
    'France international' => ['+33 6 12 34 56 78', 'FR', '+33612345678'],
]);

it('renvoie null pour une saisie invalide', function (?string $saisie, string $pays) {
    expect($this->telephones->normaliser($saisie, $pays))->toBeNull();
})->with([
    'vide' => ['', 'CI'],
    'null' => [null, 'CI'],
    'trop court' => ['070809', 'CI'],
    'ancien format à 8 chiffres' => ['08091011', 'CI'],
    'texte' => ['bonjour', 'CI'],
    'pays inconnu' => ['0708091011', 'ZZ'],
]);

it('distingue un mobile d’un fixe', function () {
    expect($this->telephones->normaliserMobile('0708091011', 'CI'))->toBe('+2250708091011')
        ->and($this->telephones->normaliserMobile('01 23 45 67 89', 'FR'))->toBeNull()
        ->and($this->telephones->normaliserMobile('06 12 34 56 78', 'FR'))->toBe('+33612345678');
});

it('affiche au format national pour un lecteur du même pays, international sinon', function () {
    expect($this->telephones->afficher('+2250708091011', 'CI'))->toBe('07 08 09 10 11')
        ->and($this->telephones->afficher('+2250708091011', 'SN'))->toBe('+225 07 08 09 10 11')
        ->and($this->telephones->afficher('+33612345678'))->toBe('+33 6 12 34 56 78')
        ->and($this->telephones->paysDe('+221771234567'))->toBe('SN');
});

it('calcule une empreinte SHA-256 sans conserver le numéro', function () {
    $empreinte = $this->telephones->empreinte('+2250708091011');

    expect($empreinte)->toHaveLength(64)
        ->not->toContain('0708091011')
        ->toBe(hash('sha256', '+2250708091011'));
});
