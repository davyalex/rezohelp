<?php

use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    referentielsAuth();
    $this->membre = Utilisateur::factory()->create();
    $this->membre->forceFill(['doit_changer_mot_de_passe' => true])->save();
});

it('oblige à changer un mot de passe temporaire avant toute autre page', function (string $page) {
    $this->actingAs($this->membre)->get($page)->assertRedirect(route('mot-de-passe.changement'));
})->with(['/', '/verification-telephone']);

it('laisse se déconnecter sans changer de mot de passe', function () {
    $this->actingAs($this->membre)->post('/deconnexion')->assertRedirect('/');
    $this->assertGuest();
});

it('enregistre le nouveau mot de passe et lève l’obligation', function () {
    $this->actingAs($this->membre)->get('/changer-mot-de-passe')->assertOk()->assertSee('choisissez-en un nouveau');

    $this->put('/changer-mot-de-passe', [
        'mot_de_passe_actuel' => 'mot-de-passe-test',
        'password' => 'Nouveau-Secret-2026',
        'password_confirmation' => 'Nouveau-Secret-2026',
    ])->assertRedirect(route('accueil'));

    $this->membre->refresh();
    expect($this->membre->doit_changer_mot_de_passe)->toBeFalse()
        ->and(Hash::check('Nouveau-Secret-2026', $this->membre->mot_de_passe))->toBeTrue();

    $this->get('/')->assertOk();
});

it('exige le mot de passe actuel et un mot de passe différent', function (array $donnees, string $champ) {
    $this->actingAs($this->membre)
        ->put('/changer-mot-de-passe', $donnees)
        ->assertSessionHasErrors($champ);

    expect($this->membre->refresh()->doit_changer_mot_de_passe)->toBeTrue();
})->with([
    'mot de passe actuel faux' => [['mot_de_passe_actuel' => 'faux', 'password' => 'Nouveau-Secret-2026', 'password_confirmation' => 'Nouveau-Secret-2026'], 'mot_de_passe_actuel'],
    'identique à l’actuel' => [['mot_de_passe_actuel' => 'mot-de-passe-test', 'password' => 'mot-de-passe-test', 'password_confirmation' => 'mot-de-passe-test'], 'password'],
]);
