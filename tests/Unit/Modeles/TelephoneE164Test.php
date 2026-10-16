<?php

use App\Models\DetailService;
use App\Models\Pays;
use App\Models\Utilisateur;
use Database\Factories\PaysFactory;

beforeEach(fn () => PaysFactory::coteDivoire());

it('stocke le téléphone d’un membre en E.164 selon son pays', function () {
    Pays::factory()->create(['code' => 'SN']);

    $ivoirien = Utilisateur::factory()->create(['pays_code' => 'CI', 'telephone' => '07 08 09 10 11']);
    $senegalais = new Utilisateur(['nom' => 'Diop', 'pays_code' => 'SN', 'mot_de_passe' => 'secret123']);
    $senegalais->telephone = '77 123 45 67';
    $senegalais->save();

    expect($ivoirien->telephone)->toBe('+2250708091011')
        ->and($senegalais->refresh()->telephone)->toBe('+221771234567');
});

it('normalise le téléphone de contact d’une annonce de service', function () {
    $detail = DetailService::factory()->create(['telephone_contact' => '05 06 07 08 09', 'whatsapp' => null]);

    expect($detail->refresh()->telephone_contact)->toBe('+2250506070809')
        ->and($detail->whatsapp)->toBeNull();
});

it('refuse d’enregistrer un numéro invalide', function () {
    Utilisateur::factory()->create(['telephone' => '1234']);
})->throws(InvalidArgumentException::class);
