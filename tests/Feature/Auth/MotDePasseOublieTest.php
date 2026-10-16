<?php

use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    referentielsAuth();
    $this->sms = fauxSms();
    $this->membre = Utilisateur::factory()->nonVerifie()->create(['telephone' => '+2250708091011']);
});

it('envoie un code de réinitialisation à un numéro inscrit', function () {
    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '07 08 09 10 11'])
        ->assertRedirect(route('mot-de-passe.reinitialisation'))
        ->assertSessionHas('statut', 'Si un compte correspond à ce numéro, un code vient de vous être envoyé par SMS.');

    expect($this->sms->dernierCode('+2250708091011'))->not->toBeNull();
});

it('répond de la même façon pour un numéro inconnu, sans envoyer de SMS', function () {
    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '01 02 03 04 05'])
        ->assertRedirect(route('mot-de-passe.reinitialisation'))
        ->assertSessionHas('statut', 'Si un compte correspond à ce numéro, un code vient de vous être envoyé par SMS.');

    expect($this->sms->envoyes)->toBe([]);
});

it('change le mot de passe avec le bon code, puis permet de se connecter', function () {
    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '0708091011']);
    $this->get('/reinitialiser-mot-de-passe')->assertOk()->assertSee('+225 07 08 09 10 11');

    $this->post('/reinitialiser-mot-de-passe', [
        'code' => $this->sms->dernierCode('+2250708091011'),
        'password' => 'Nouveau-Secret-2026',
        'password_confirmation' => 'Nouveau-Secret-2026',
    ])->assertRedirect(route('login'))->assertSessionHas('statut');

    $this->membre->refresh();
    expect(Hash::check('Nouveau-Secret-2026', $this->membre->mot_de_passe))->toBeTrue()
        ->and($this->membre->telephone_verifie_le)->not->toBeNull();

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '+225 07 08 09 10 11', 'password' => 'Nouveau-Secret-2026'])
        ->assertRedirect('/');
    $this->assertAuthenticatedAs($this->membre);
});

it('refuse un code faux ou expiré', function (bool $expire) {
    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '0708091011']);
    $code = $this->sms->dernierCode('+2250708091011');

    if ($expire) {
        $this->travel(11)->minutes();
    } else {
        $code = $code === '000000' ? '111111' : '000000';
    }

    $this->post('/reinitialiser-mot-de-passe', [
        'code' => $code,
        'password' => 'Nouveau-Secret-2026',
        'password_confirmation' => 'Nouveau-Secret-2026',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('mot-de-passe-test', $this->membre->refresh()->mot_de_passe))->toBeTrue();
})->with(['code faux' => false, 'code expiré' => true]);

it('refuse un nouveau mot de passe compromis', function () {
    $this->declarerCompromis('Password-1234');

    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '0708091011']);

    $this->post('/reinitialiser-mot-de-passe', [
        'code' => $this->sms->dernierCode('+2250708091011'),
        'password' => 'Password-1234',
        'password_confirmation' => 'Password-1234',
    ])->assertSessionHasErrors('password');
});

it('renvoie vers la demande de code sans numéro en session', function () {
    $this->get('/reinitialiser-mot-de-passe')->assertRedirect(route('mot-de-passe.oublie'));
    $this->post('/reinitialiser-mot-de-passe', ['code' => '123456'])->assertForbidden();
});

it('limite les demandes de code à 5 par minute pour un même numéro', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '0708091011'])->assertStatus(302);
    }

    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '+225 07 08 09 10 11'])->assertStatus(429);
    expect($this->sms->envoyes)->toHaveCount(5);
});

it('ferme les autres sessions du membre après la réinitialisation', function () {
    DB::table('sessions')->insert([
        'id' => 'session-ailleurs',
        'user_id' => $this->membre->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Téléphone volé',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->post('/mot-de-passe-oublie', ['pays_code' => 'CI', 'telephone' => '0708091011']);
    $this->post('/reinitialiser-mot-de-passe', [
        'code' => $this->sms->dernierCode('+2250708091011'),
        'password' => 'Nouveau-Secret-2026',
        'password_confirmation' => 'Nouveau-Secret-2026',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $this->membre->id)->count())->toBe(0);
});
