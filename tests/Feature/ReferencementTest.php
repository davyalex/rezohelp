<?php

use App\Models\Publication;

it('liste les publications en ligne dans le sitemap', function () {
    $enLigne = Publication::factory()->publiee()->create();
    $enAttente = Publication::factory()->enAttente()->create();

    $reponse = $this->get('/sitemap.xml')->assertOk();

    expect($reponse->headers->get('Content-Type'))->toContain('application/xml');
    $xml = simplexml_load_string((string) $reponse->getContent());
    $urls = array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []);

    expect($urls)->toContain(route('publications.show', $enLigne->slug))
        ->toContain(route('fil.emplois'))
        ->not->toContain(route('publications.show', $enAttente->slug));
});

it('sert un robots.txt qui exclut les espaces privés', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /mon-espace')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.route('referencement.sitemap'));
});
