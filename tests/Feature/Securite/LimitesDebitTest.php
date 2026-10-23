<?php

use App\Models\Utilisateur;
use Illuminate\Testing\TestResponse;

/**
 * Lot 9 : limites de débit absentes du reste de la suite (l'inscription : connexion, OTP et
 * publication sont couverts par leurs propres tests).
 */
beforeEach(function () {
    referentielsAuth();
    fauxSms();
});

function inscrire(string $telephone, string $motDePasse = 'Abidjan-Cocody-2026'): TestResponse
{
    $reponse = test()->post('/inscription', [
        'prenoms' => 'Awa', 'nom' => 'Koné', 'pays_code' => 'CI', 'telephone' => $telephone,
        'password' => $motDePasse, 'password_confirmation' => $motDePasse,
    ]);
    auth()->logout();

    return $reponse;
}

it('limite les inscriptions depuis une même connexion, sans compter les erreurs de saisie', function () {
    parametre('max_inscriptions_par_heure', '2');

    inscrire('07 08 09 10 11', 'court')->assertSessionHasErrors('password');
    inscrire('07 08 09 10 11')->assertSessionHasNoErrors();
    inscrire('05 06 07 08 09')->assertSessionHasNoErrors();
    inscrire('01 02 03 04 05')->assertSessionHasErrors(['telephone' => 'Trop de comptes créés depuis cette connexion. Réessayez dans 60 minute(s).']);

    expect(Utilisateur::query()->count())->toBe(2);
});

it('ne limite pas les inscriptions quand max_inscriptions_par_heure vaut 0', function () {
    parametre('max_inscriptions_par_heure', '0');

    foreach (['07 08 09 10 11', '05 06 07 08 09', '01 02 03 04 05'] as $telephone) {
        inscrire($telephone)->assertSessionHasNoErrors();
    }

    expect(Utilisateur::query()->count())->toBe(3);
});
