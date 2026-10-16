<?php

use App\Enums\TypeParametre;
use App\Models\Commune;
use App\Models\Parametre;
use App\Services\CacheFil;
use Illuminate\Support\Facades\Cache;

it('convertit la valeur selon le type du paramètre', function (TypeParametre $type, string $brut, mixed $attendu) {
    Parametre::factory()->create(['cle' => 'essai_type', 'type' => $type, 'valeur' => $brut]);

    expect(Parametre::valeur('essai_type'))->toBe($attendu);
})->with([
    'entier' => [TypeParametre::Entier, '70', 70],
    'booléen faux' => [TypeParametre::Booleen, '0', false],
    'booléen vrai' => [TypeParametre::Booleen, '1', true],
    'texte' => [TypeParametre::Texte, 'Bonjour', 'Bonjour'],
    'json' => [TypeParametre::Json, '{"cdi":3000000}', ['cdi' => 3000000]],
]);

it('expose des accesseurs typés', function () {
    Parametre::factory()->create(['cle' => 'essai_entier', 'type' => TypeParametre::Entier, 'valeur' => '5']);
    Parametre::factory()->create(['cle' => 'essai_bool', 'type' => TypeParametre::Booleen, 'valeur' => '1']);
    Parametre::factory()->create(['cle' => 'essai_liste', 'type' => TypeParametre::Json, 'valeur' => '["a","b"]']);

    expect(Parametre::entier('essai_entier'))->toBe(5)
        ->and(Parametre::booleen('essai_bool'))->toBeTrue()
        ->and(Parametre::texte('essai_entier'))->toBe('5')
        ->and(Parametre::liste('essai_liste'))->toBe(['a', 'b']);
});

it('refuse une clé inconnue', function () {
    Parametre::valeur('cle_inexistante');
})->throws(InvalidArgumentException::class, 'cle_inexistante');

it('met les paramètres en cache sous ref:parametres et invalide à la modification', function () {
    $parametre = Parametre::factory()->create(['cle' => 'essai_cache', 'type' => TypeParametre::Entier, 'valeur' => '1']);

    expect(Parametre::entier('essai_cache'))->toBe(1)
        ->and(Cache::has('ref:parametres'))->toBeTrue();

    $parametre->update(['valeur' => '2']);

    expect(Cache::has('ref:parametres'))->toBeFalse()
        ->and(Parametre::entier('essai_cache'))->toBe(2);
});

it('invalide le référentiel des communes à chaque modification', function () {
    $cache = app(CacheFil::class);
    $cache->referentiel('communes', fn (): array => ['en cache']);

    Commune::factory()->create();

    expect(Cache::has('ref:communes'))->toBeFalse();

    $cache->referentiel('communes', fn (): array => ['en cache']);
    Commune::query()->firstOrFail()->delete();

    expect(Cache::has('ref:communes'))->toBeFalse();
});
