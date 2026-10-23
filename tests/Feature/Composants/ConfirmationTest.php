<?php

use App\Models\Alerte;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Les actions destructives du site demandent confirmation dans une boîte du site
 * (<dialog>, resources/js/confirmation.js), jamais avec la boîte du navigateur.
 */
beforeEach(fn () => referentielsPublication());

it('sert la boîte de confirmation du site sur les pages publiques', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<dialog id="dialogue-confirmation"', false)
        ->assertSee('value="confirmer"', false)
        ->assertSee('value="annuler"', false);
});

it('demande confirmation dans la boîte du site pour supprimer une alerte ou une publication', function (string $route) {
    $membre = Utilisateur::factory()->create();
    Alerte::factory()->create(['utilisateur_id' => $membre->id, 'nom' => 'Plombier Cocody']);
    Publication::factory()->publiee()->create(['auteur_id' => $membre->id]);

    $html = (string) $this->actingAs($membre)->get(route($route))->assertOk()->getContent();

    expect($html)->toContain('data-confirmer="Supprimer')
        ->and($html)->toContain('data-danger')
        ->and($html)->not->toContain('confirm(');
})->with(['alertes.index', 'espace.publications.index']);

it('n’utilise plus la boîte de confirmation du navigateur dans les vues du site', function () {
    $vues = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($fichier) => str_contains($fichier->getPathname(), DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR))
        ->filter(fn ($fichier) => str_contains($fichier->getContents(), 'confirm('))
        ->map(fn ($fichier) => $fichier->getRelativePathname())
        ->values()
        ->all();

    expect($vues)->toBe([]);
});
