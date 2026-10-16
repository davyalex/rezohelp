<?php

use App\Models\Pays;
use App\View\Components\ChampTelephone;
use Database\Seeders\PaysSeeder;

beforeEach(fn () => $this->seed(PaysSeeder::class));

it('propose les pays actifs avec la Côte d’Ivoire par défaut', function () {
    Pays::query()->where('code', 'FR')->update(['actif' => false]);

    $html = (string) $this->withViewErrors([])->blade('<x-champ-telephone />');

    expect($html)->toContain('<option value="CI" selected')
        ->toContain('🇨🇮 +225')
        ->toContain('+221')
        ->not->toContain('value="FR"')
        ->toContain('inputmode="tel"')
        ->toContain('Votre numéro ne sera jamais affiché publiquement.');
});

it('construit le drapeau emoji depuis le code ISO', function () {
    expect(ChampTelephone::drapeau('CI'))->toBe('🇨🇮')
        ->and(ChampTelephone::drapeau('sn'))->toBe('🇸🇳');
});
