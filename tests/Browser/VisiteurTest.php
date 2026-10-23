<?php

use App\Models\Commune;
use App\Models\Publication;

/**
 * Lot 10, parcours 1 : visiteur → fil → filtre → détail → partage (dans un vrai navigateur).
 */
beforeEach(fn () => referentielsPublication());

it('parcourt le fil, filtre par commune, ouvre une offre et la partage', function () {
    $cocody = Commune::query()->where('slug', 'cocody')->value('id');
    $plateau = Commune::query()->where('slug', 'plateau')->value('id');
    $offre = Publication::factory()->emploi()->publiee()->create(['titre' => 'Comptable confirmé à Cocody', 'commune_id' => $cocody]);
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Caissière au Plateau', 'commune_id' => $plateau]);
    Publication::factory()->serviceOffre()->publiee()->create(['titre' => 'Électricien disponible']);

    $page = visit('/');

    $page->assertSee('Comptable confirmé à Cocody')
        ->assertSee('Électricien disponible')
        ->click('nav[aria-label="Types de publication"] >> text=Emplois')
        ->assertPathIs('/emplois')
        ->assertDontSee('Électricien disponible')
        ->click('[aria-controls="choix-commune"]')
        ->click('#choix-commune >> text=Cocody')
        ->assertQueryStringHas('commune', 'cocody')
        ->assertSee('Comptable confirmé à Cocody')
        ->assertDontSee('Caissière au Plateau')
        ->click('Comptable confirmé à Cocody')
        ->assertPathIs('/publication/'.$offre->slug)
        ->assertSee('Description')
        ->assertAttribute('a[href$="/partager"]', 'target', '_blank')
        ->assertSee('Partager sur WhatsApp')
        ->assertNoJavaScriptErrors();
});

it('affiche le fil sur un téléphone, avec la navigation basse', function () {
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Chauffeur livreur à Yopougon']);

    visit('/')->on()->mobile()
        ->assertSee('Chauffeur livreur à Yopougon')
        ->assertVisible('nav[aria-label="Navigation principale"]')
        ->assertNoJavaScriptErrors();
});
