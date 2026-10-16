<?php

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Enums\ObjetOtp;
use App\Models\Utilisateur;

beforeEach(function () {
    referentielsAuth();
    $this->sms = fauxSms();
    $this->membre = Utilisateur::factory()->nonVerifie()->create(['telephone' => '+2250708091011']);
    app(EnvoyerCodeOtp::class)->executer('+2250708091011', ObjetOtp::Verification, $this->membre);
    $this->code = $this->sms->dernierCode('+2250708091011');
});

it('exige d’être connecté', function () {
    $this->get('/verification-telephone')->assertRedirect(route('login'));
    $this->post('/verification-telephone', ['code' => $this->code])->assertRedirect(route('login'));
});

it('affiche le numéro au format national', function () {
    $this->actingAs($this->membre)->get('/verification-telephone')
        ->assertOk()
        ->assertSee('07 08 09 10 11');
});

it('vérifie le numéro avec le bon code', function () {
    $this->actingAs($this->membre)
        ->post('/verification-telephone', ['code' => $this->code])
        ->assertRedirect(route('fil.index'))
        ->assertSessionHas('statut');

    expect($this->membre->refresh()->telephone_verifie_le)->not->toBeNull();
});

it('refuse un code faux', function () {
    $faux = $this->code === '000000' ? '111111' : '000000';

    $this->actingAs($this->membre)
        ->post('/verification-telephone', ['code' => $faux])
        ->assertSessionHasErrors(['code' => 'Ce code est incorrect.']);

    expect($this->membre->refresh()->telephone_verifie_le)->toBeNull();
});

it('refuse un code expiré après 10 minutes', function () {
    $this->travel(11)->minutes();

    $this->actingAs($this->membre)
        ->post('/verification-telephone', ['code' => $this->code])
        ->assertSessionHasErrors(['code' => 'Ce code a expiré. Demandez-en un nouveau.']);

    expect($this->membre->refresh()->telephone_verifie_le)->toBeNull();
});

it('renvoie un nouveau code et invalide le précédent', function () {
    $ancien = $this->code;

    $this->actingAs($this->membre)->post('/verification-telephone/code')->assertSessionHas('statut');
    $nouveau = $this->sms->dernierCode('+2250708091011');

    expect($this->sms->envoyes)->toHaveCount(2);

    if ($ancien !== $nouveau) {
        $this->post('/verification-telephone', ['code' => $ancien])->assertSessionHasErrors('code');
    }

    $this->post('/verification-telephone', ['code' => $nouveau])->assertSessionHasNoErrors();
    expect($this->membre->refresh()->telephone_verifie_le)->not->toBeNull();
});

it('limite les envois et vérifications de code à 5 par minute', function () {
    $faux = $this->code === '000000' ? '111111' : '000000';
    $this->actingAs($this->membre);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/verification-telephone', ['code' => $faux])->assertStatus(302);
    }

    $this->post('/verification-telephone', ['code' => $this->code])->assertStatus(429);
});

it('ne renvoie pas de code à un numéro déjà vérifié', function () {
    $this->membre->forceFill(['telephone_verifie_le' => now()])->save();

    $this->actingAs($this->membre)->post('/verification-telephone/code');
    $this->get('/verification-telephone')->assertRedirect(route('fil.index'));

    expect($this->sms->envoyes)->toHaveCount(1);
});
