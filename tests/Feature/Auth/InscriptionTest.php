<?php

use App\Enums\MotifSms;
use App\Models\CodeOtp;
use App\Models\Pays;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;

beforeEach(function () {
    referentielsAuth();
    $this->sms = fauxSms();
});

function donneesInscription(array $surcharge = []): array
{
    return array_merge([
        'prenoms' => 'Awa',
        'nom' => 'Koné',
        'pays_code' => 'CI',
        'telephone' => '07 08 09 10 11',
        'password' => 'Abidjan-Cocody-2026',
        'password_confirmation' => 'Abidjan-Cocody-2026',
    ], $surcharge);
}

it('affiche le formulaire d’inscription avec le sélecteur de pays', function () {
    $this->get('/inscription')
        ->assertOk()
        ->assertSee('Créer un compte')
        ->assertSee('name="pays_code"', false)
        ->assertSee('+225')
        ->assertSee('Côte d’Ivoire');
});

it('crée le compte en E.164, connecte le membre et envoie un code de vérification', function () {
    $this->post('/inscription', donneesInscription())
        ->assertRedirect(route('telephone.verification'));

    $utilisateur = Utilisateur::query()->sole();

    expect($utilisateur->telephone)->toBe('+2250708091011')
        ->and($utilisateur->pays_code)->toBe('CI')
        ->and($utilisateur->telephone_verifie_le)->toBeNull()
        ->and($utilisateur->hasRole('membre'))->toBeTrue()
        ->and($this->sms->dernierCode('+2250708091011'))->toMatch('/^\d{6}$/')
        ->and(CodeOtp::query()->count())->toBe(1);

    $this->assertAuthenticatedAs($utilisateur);

    // Le journal des SMS ne conserve jamais le code en clair.
    $journal = SmsEnvoye::query()->sole();
    expect($journal->motif)->toBe(MotifSms::Otp)
        ->and($journal->message)->not->toContain($this->sms->dernierCode('+2250708091011'))
        ->and($journal->message)->toContain('******');
});

it('inscrit des numéros sénégalais et français', function (string $pays, string $saisie, string $attendu) {
    $this->post('/inscription', donneesInscription(['pays_code' => $pays, 'telephone' => $saisie]))
        ->assertSessionHasNoErrors();

    expect(Utilisateur::query()->sole()->telephone)->toBe($attendu);
})->with([
    'Sénégal' => ['SN', '77 123 45 67', '+221771234567'],
    'France' => ['FR', '06 12 34 56 78', '+33612345678'],
]);

it('refuse un numéro déjà inscrit, même saisi dans un autre format', function (string $autreFormat) {
    Utilisateur::factory()->create(['telephone' => '+2250708091011']);

    $this->post('/inscription', donneesInscription(['telephone' => $autreFormat]))
        ->assertSessionHasErrors(['telephone' => 'Ce numéro de téléphone est déjà utilisé par un compte.']);

    expect(Utilisateur::query()->count())->toBe(1);
    $this->assertGuest();
})->with(['0708091011', '07 08 09 10 11', '+225 07 08 09 10 11']);

it('refuse un numéro invalide ou fixe', function (string $saisie) {
    $this->post('/inscription', donneesInscription(['telephone' => $saisie]))
        ->assertSessionHasErrors('telephone');

    expect(Utilisateur::query()->count())->toBe(0);
})->with(['1234', '08091011', 'bonjour']);

it('refuse un pays inactif', function () {
    Pays::query()->where('code', 'SN')->update(['actif' => false]);

    $this->post('/inscription', donneesInscription(['pays_code' => 'SN', 'telephone' => '77 123 45 67']))
        ->assertSessionHasErrors('pays_code');
});

it('refuse un mot de passe trop court ou mal confirmé', function (array $surcharge) {
    $this->post('/inscription', donneesInscription($surcharge))->assertSessionHasErrors('password');
})->with([
    'trop court' => [['password' => 'court', 'password_confirmation' => 'court']],
    'confirmation différente' => [['password_confirmation' => 'Autre-Mot-De-Passe-2026']],
]);

it('refuse un mot de passe apparu dans une fuite de données', function () {
    $this->declarerCompromis('Abidjan-Cocody-2026');

    $this->post('/inscription', donneesInscription())
        ->assertSessionHasErrors(['password' => 'Ce mot de passe est apparu dans une fuite de données. Merci d’en choisir un autre.']);

    expect(Utilisateur::query()->count())->toBe(0);
});

it('ramène le membre vers la page demandée après inscription et vérification', function () {
    $this->get('/verification-telephone')->assertRedirect(route('login'));

    // L'utilisateur arrivait d'un lien protégé, avec des filtres dans l'URL.
    session()->put('url.intended', url('/mon-espace/alertes/creer?type=service&commune=cocody'));

    $this->post('/inscription', donneesInscription())->assertRedirect(route('telephone.verification'));

    $code = $this->sms->dernierCode('+2250708091011');

    $this->post('/verification-telephone', ['code' => $code])
        ->assertRedirect(url('/mon-espace/alertes/creer?type=service&commune=cocody'));
});
