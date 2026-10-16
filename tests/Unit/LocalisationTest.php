<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

it('utilise le français et le fuseau d’Abidjan', function () {
    expect(app()->getLocale())->toBe('fr')
        ->and(config('app.timezone'))->toBe('Africa/Abidjan')
        ->and(date_default_timezone_get())->toBe('Africa/Abidjan');
});

it('affiche les dates relatives en français', function () {
    Carbon::setTestNow('2026-10-16 12:00:00');

    expect(Carbon::parse('2026-10-16 10:00:00')->diffForHumans())->toBe('il y a 2 heures');
});

it('traduit les messages de validation en français', function () {
    $validateur = Validator::make(['telephone' => ''], ['telephone' => 'required']);

    expect($validateur->errors()->first('telephone'))->toBe('Le champ « téléphone » est obligatoire.');
});
