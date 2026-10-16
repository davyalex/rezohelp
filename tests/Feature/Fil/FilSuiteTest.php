<?php

use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\ParametresSeeder;

beforeEach(fn () => $this->seed(ParametresSeeder::class));

it('renvoie un fragment HTML et le curseur suivant dans X-Curseur-Suivant', function () {
    Publication::factory()->count(30)->sequence(fn ($sequence) => [])->publiee()->create();

    $page = (string) $this->get('/')->getContent();
    preg_match('/data-curseur="([^"]+)"/', $page, $curseur);

    $reponse = $this->get('/fil/suite?curseur='.$curseur[1])->assertOk();
    $suivant = $reponse->headers->get('X-Curseur-Suivant');

    expect(substr_count((string) $reponse->getContent(), '<article'))->toBe(12)
        ->and((string) $reponse->getContent())->not->toContain('<html')
        ->and($suivant)->not->toBe('');

    $derniere = $this->get('/fil/suite?curseur='.$suivant)->assertOk();
    expect(substr_count((string) $derniere->getContent(), '<article'))->toBe(6)
        ->and($derniere->headers->get('X-Curseur-Suivant'))->toBe('');
});

it('autorise le cache navigateur pour un visiteur, jamais pour un membre', function () {
    Publication::factory()->publiee()->create();

    expect($this->get('/fil/suite')->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60');

    $prive = $this->actingAs(Utilisateur::factory()->create())->get('/fil/suite')->headers->get('Cache-Control');
    expect($prive)->toContain('no-store')->not->toContain('public');
});

it('ignore un curseur invalide et repart du début', function () {
    $publication = Publication::factory()->publiee()->create();

    $this->get('/fil/suite?curseur=nimporte-quoi')->assertOk()->assertSee($publication->titre);
});

it('compte les nouvelles publications parues depuis la première carte', function () {
    $depuis = now()->subMinutes(10);
    Publication::factory()->publiee($depuis)->create();
    Publication::factory()->count(2)->publiee(now()->subMinute())->create();

    $this->getJson('/fil/nouvelles?depuis='.urlencode($depuis->toIso8601String()))
        ->assertOk()
        ->assertExactJson(['nombre' => 2]);
});

it('n’affiche jamais le téléphone d’un membre dans le fil', function () {
    $auteur = Utilisateur::factory()->create(['telephone' => '+2250708091011']);
    Publication::factory()->serviceDemande()->publiee()->create(['auteur_id' => $auteur->id]);

    $this->get('/')->assertOk()->assertDontSee('0708091011')->assertDontSee('07 08 09 10 11');
});
