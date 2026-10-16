<?php

use App\Models\Commentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;

beforeEach(function () {
    $this->seed([ParametresSeeder::class, RolesEtPermissionsSeeder::class]);
    $this->membre = Utilisateur::factory()->create();
    $this->publication = Publication::factory()->publiee()->create(['nb_jaime' => 0, 'nb_commentaires' => 0]);
});

it('ajoute puis retire un J’aime (réponse JSON pour l’interface optimiste)', function () {
    $this->actingAs($this->membre)
        ->postJson('/publication/'.$this->publication->id.'/jaime')
        ->assertOk()->assertExactJson(['aime' => true, 'nb_jaime' => 1]);

    $this->postJson('/publication/'.$this->publication->id.'/jaime')
        ->assertOk()->assertExactJson(['aime' => false, 'nb_jaime' => 0]);

    expect($this->publication->reactions()->count())->toBe(0);
});

it('fonctionne sans JavaScript avec une redirection', function () {
    $this->actingAs($this->membre)
        ->from('/')
        ->post('/publication/'.$this->publication->id.'/jaime')
        ->assertRedirect('/');

    expect($this->publication->refresh()->nb_jaime)->toBe(1);
});

it('demande de se connecter pour aimer, enregistrer, commenter ou signaler', function (string $url) {
    $this->post($url)->assertRedirect(route('login'));
    $this->postJson($url)->assertUnauthorized();
})->with([
    'jaime' => fn () => '/publication/'.$this->publication->id.'/jaime',
    'enregistrer' => fn () => '/publication/'.$this->publication->id.'/enregistrer',
    'commenter' => fn () => '/publication/'.$this->publication->id.'/commentaires',
    'signaler' => fn () => '/publication/'.$this->publication->id.'/signaler',
]);

it('refuse les interactions sur une publication non publiée ou à un compte suspendu', function () {
    $enAttente = Publication::factory()->enAttente()->create();
    $this->actingAs($this->membre)->postJson('/publication/'.$enAttente->id.'/jaime')->assertForbidden();

    $suspendu = Utilisateur::factory()->suspendu()->create(['suspendu_jusqu_au' => now()->addDay()]);
    // Le middleware déconnecte le compte suspendu avant toute action.
    $this->actingAs($suspendu)->post('/publication/'.$this->publication->id.'/jaime')->assertRedirect(route('login'));
    expect($this->publication->refresh()->nb_jaime)->toBe(0);
});

it('enregistre et retire une publication des enregistrés', function () {
    $this->actingAs($this->membre)
        ->postJson('/publication/'.$this->publication->id.'/enregistrer')
        ->assertExactJson(['actif' => true]);
    expect($this->membre->publicationsEnregistrees()->pluck('publications.id')->all())->toBe([$this->publication->id]);

    $this->postJson('/publication/'.$this->publication->id.'/enregistrer')->assertExactJson(['actif' => false]);
    expect($this->membre->publicationsEnregistrees()->count())->toBe(0);
});

it('publie un commentaire et une réponse, et tient le compteur à jour', function () {
    $this->actingAs($this->membre)
        ->post('/publication/'.$this->publication->id.'/commentaires', ['contenu' => 'Toujours disponible ?'])
        ->assertRedirect();
    $commentaire = Commentaire::query()->sole();

    $autre = Utilisateur::factory()->create();
    $this->actingAs($autre)->post('/publication/'.$this->publication->id.'/commentaires', ['contenu' => 'Oui, écrivez-lui.', 'parent_id' => $commentaire->id]);

    $reponse = Commentaire::query()->where('utilisateur_id', $autre->id)->sole();
    expect($reponse->parent_id)->toBe($commentaire->id)
        ->and($this->publication->refresh()->nb_commentaires)->toBe(2);

    $this->get('/publication/'.$this->publication->slug)->assertSee('Toujours disponible ?')->assertSee('Oui, écrivez-lui.');
});

it('valide le commentaire et refuse un parent d’une autre publication', function () {
    $ailleurs = Commentaire::factory()->create();

    $this->actingAs($this->membre)
        ->post('/publication/'.$this->publication->id.'/commentaires', ['contenu' => ''])
        ->assertSessionHasErrors('contenu');

    $this->post('/publication/'.$this->publication->id.'/commentaires', ['contenu' => 'Bonjour', 'parent_id' => $ailleurs->id])
        ->assertSessionHasErrors('parent_id');
});

it('supprime ses propres commentaires, pas ceux des autres', function () {
    $mien = Commentaire::factory()->create(['publication_id' => $this->publication->id, 'utilisateur_id' => $this->membre->id]);
    Commentaire::factory()->create(['publication_id' => $this->publication->id, 'parent_id' => $mien->id]);
    $autre = Commentaire::factory()->create(['publication_id' => $this->publication->id]);
    $this->publication->forceFill(['nb_commentaires' => 3])->saveQuietly();

    $this->actingAs($this->membre)->delete('/commentaires/'.$autre->id)->assertForbidden();
    $this->delete('/commentaires/'.$mien->id)->assertRedirect();

    expect(Commentaire::query()->pluck('id')->all())->toBe([$autre->id])
        ->and($this->publication->refresh()->nb_commentaires)->toBe(1);
});

it('laisse la modération supprimer un commentaire', function () {
    $commentaire = Commentaire::factory()->create(['publication_id' => $this->publication->id]);
    $moderateur = Utilisateur::factory()->create();
    $moderateur->assignRole('moderateur');

    $this->actingAs($moderateur)->delete('/commentaires/'.$commentaire->id)->assertRedirect();

    expect(Commentaire::query()->count())->toBe(0);
});
