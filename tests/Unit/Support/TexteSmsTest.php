<?php

use App\Support\TexteSms;

it('translittère les accents et la typographie vers l’alphabet GSM-7', function () {
    expect(TexteSms::preparer('Électricien à Cocody — « dispo » aujourd’hui… 5 000 FCFA'))
        ->toBe('Electricien a Cocody - "dispo" aujourd\'hui... 5 000 FCFA');
});

it('remplace les caractères hors GSM-7 (emoji, symboles) et resserre les espaces', function () {
    expect(TexteSms::preparer('Offre 🔥 urgente   ✓ ok'))->toBe('Offre urgente ok');
});

it('ne dépasse jamais 160 caractères et coupe proprement', function () {
    $texte = TexteSms::preparer(str_repeat('Plombier disponible rapidement ', 10));

    expect(strlen($texte))->toBeLessThanOrEqual(160)
        ->and($texte)->toEndWith('...')
        ->and(mb_check_encoding($texte, 'ASCII'))->toBeTrue();
});

it('garde toujours le lien court entier dans un SMS d’alerte', function () {
    $sms = TexteSms::alerte(str_repeat('Électricien très expérimenté disponible ', 8), 'Port-Bouët', 'https://rezohelp.ci/p/ab12cd');

    expect(strlen($sms))->toBeLessThanOrEqual(160)
        ->and($sms)->toStartWith('Rezohelp: Electricien')
        ->and($sms)->toEndWith(', Port-Bouet. Voir: rezohelp.ci/p/ab12cd');
});

it('compose un SMS d’alerte court sans commune', function () {
    expect(TexteSms::alerte('Comptable CDI', null, 'http://rezohelp.test/p/zz99aa'))
        ->toBe('Rezohelp: Comptable CDI. Voir: rezohelp.test/p/zz99aa');
});
