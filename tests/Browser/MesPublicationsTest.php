<?php

use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * « Mes publications » : actions regroupées dans un menu déroulant, clôture par une boîte de dialogue.
 */
beforeEach(fn () => referentielsPublication());

it('regroupe les actions dans un menu et clôture une publication avec son motif', function () {
    $auteur = Utilisateur::factory()->create();
    $publication = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $auteur->id, 'titre' => 'Électricien disponible à Cocody']);

    $this->actingAs($auteur);
    $page = visit('/mon-espace/publications');

    $page->assertSee('Électricien disponible à Cocody')
        ->assertSee('Modifier')
        ->assertDontSee('Dupliquer')
        ->click('[aria-controls="actions-'.$publication->id.'"]')
        ->assertSee('Dupliquer')
        ->assertSee('Prolonger')
        ->assertSee('Supprimer')
        ->click('#actions-'.$publication->id.' >> text=Clôturer…')
        ->assertSee('Clôturer cette publication ?')
        ->click('dialog[open] button[type="submit"]')
        ->assertNoJavaScriptErrors();

    expect($publication->refresh()->statut)->toBe(StatutPublication::Cloturee);
});
