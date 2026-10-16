<?php

use App\Models\Utilisateur;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    referentielsAuth();
    $this->membre = Utilisateur::factory()->create(['telephone' => '+2250708091011', 'derniere_connexion_le' => null]);
});

it('affiche le formulaire de connexion en français', function () {
    $this->get('/connexion')
        ->assertOk()
        ->assertSee('Se connecter')
        ->assertSee('Mot de passe oublié ?')
        ->assertSee('Rester connecté');
});

it('connecte le même compte quel que soit le format du numéro', function (string $saisie) {
    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => $saisie, 'password' => 'mot-de-passe-test'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($this->membre);
    expect($this->membre->refresh()->derniere_connexion_le)->not->toBeNull();
})->with(['0708091011', '07 08 09 10 11', '+225 07 08 09 10 11']);

it('connecte un membre sénégalais avec son numéro national', function () {
    $senegalais = Utilisateur::factory()->create(['pays_code' => 'SN', 'telephone' => '+221771234567']);

    $this->post('/connexion', ['pays_code' => 'SN', 'telephone' => '77 123 45 67', 'password' => 'mot-de-passe-test'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($senegalais);
});

it('refuse un mauvais mot de passe ou un numéro inconnu', function (string $telephone, string $motDePasse) {
    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => $telephone, 'password' => $motDePasse])
        ->assertSessionHasErrors(['telephone' => 'Ce numéro de téléphone et ce mot de passe ne correspondent à aucun compte.']);

    $this->assertGuest();
})->with([
    'mauvais mot de passe' => ['0708091011', 'mauvais-mot-de-passe'],
    'numéro inconnu' => ['0102030405', 'mot-de-passe-test'],
    'numéro invalide' => ['1234', 'mot-de-passe-test'],
]);

it('refuse un compte suspendu avec un message clair', function () {
    $this->membre->forceFill([
        'suspendu_le' => now(),
        'suspendu_jusqu_au' => now()->addDays(7),
        'motif_suspension' => 'Annonces frauduleuses',
    ])->save();

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])
        ->assertSessionHasErrors('telephone');

    $erreur = session('errors')->first('telephone');
    expect($erreur)->toContain('Votre compte est suspendu jusqu’au')
        ->and($erreur)->toContain('Motif : Annonces frauduleuses');
    $this->assertGuest();
});

it('accepte de nouveau un compte dont la suspension est terminée', function () {
    $this->membre->forceFill(['suspendu_le' => now()->subDays(10), 'suspendu_jusqu_au' => now()->subDay()])->save();

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($this->membre);
});

it('déconnecte un membre suspendu pendant sa session', function () {
    $this->actingAs($this->membre);
    $this->membre->forceFill(['suspendu_le' => now()])->save();

    $this->get('/')->assertRedirect(route('login'));

    $this->assertGuest();
});

it('limite à 5 tentatives par minute pour un même numéro, tous formats confondus', function () {
    $formats = ['0708091011', '07 08 09 10 11', '+225 07 08 09 10 11', '0708091011', '07.08.09.10.11'];

    foreach ($formats as $format) {
        $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => $format, 'password' => 'mauvais'])->assertStatus(302);
    }

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('mémorise la connexion avec « Rester connecté »', function () {
    $reponse = $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test', 'remember' => '1']);

    $reponse->assertRedirect('/');
    expect(collect($reponse->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')))->toBeTrue();
});

it('ramène vers la page protégée demandée, filtres compris', function () {
    Route::middleware(['web', 'auth'])->get('/essai-protege', fn () => 'ok');

    $this->get('/essai-protege?type=service&commune=cocody')->assertRedirect(route('login'));

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])
        ->assertRedirect(url('/essai-protege?commune=cocody&type=service'));
});

it('déconnecte le membre', function () {
    $this->actingAs($this->membre)->post('/deconnexion')->assertRedirect('/');

    $this->assertGuest();
});

it('redirige un membre connecté qui ouvre la page de connexion', function () {
    $this->actingAs($this->membre)->get('/connexion')->assertRedirect('/');
});
