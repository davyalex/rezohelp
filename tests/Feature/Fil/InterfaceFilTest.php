<?php

use App\Enums\TypeContrat;
use App\Models\Categorie;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\FilService;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PaysSeeder;

beforeEach(function () {
    $this->seed([PaysSeeder::class, CommunesSeeder::class, CategoriesSeeder::class, ParametresSeeder::class]);
});

it('compte les offres d’emploi en ligne par type de contrat, dans l’ordre de l’enum', function () {
    Publication::factory()->emploi()->publiee()->count(2)->create(['type_contrat' => TypeContrat::Stage]);
    Publication::factory()->emploi()->publiee()->create(['type_contrat' => TypeContrat::Cdi]);
    Publication::factory()->emploi()->create(['type_contrat' => TypeContrat::Cdd]); // non publiée : ignorée

    expect(app(FilService::class)->offresParContrat())->toBe([
        ['contrat' => 'cdi', 'libelle' => 'CDI', 'nombre' => 1],
        ['contrat' => 'stage', 'libelle' => 'Stage', 'nombre' => 2],
    ]);
});

it('compte les publications par catégorie principale, sous-catégories incluses', function () {
    $batiment = Categorie::query()->where('slug', 'service-batiment-et-reparations')->firstOrFail();
    $plomberie = Categorie::query()->where('slug', 'service-plomberie')->firstOrFail();
    Publication::factory()->serviceOffre()->publiee()->create(['categorie_id' => $batiment->id]);
    Publication::factory()->serviceOffre()->publiee()->count(2)->create(['categorie_id' => $plomberie->id]);

    expect(app(FilService::class)->nombresParCategorie())->toMatchArray([$batiment->id => 3]);
});

it('affiche les emplois par contrat dans la colonne de droite à la place des communes populaires', function () {
    Publication::factory()->emploi()->publiee()->count(3)->create(['type_contrat' => TypeContrat::Cdi]);

    $this->get('/')->assertOk()
        ->assertSee('Emplois par contrat')
        ->assertSeeText('3 offres disponibles')
        ->assertDontSee('Communes populaires');
});

it('regroupe les catégories par type dans des groupes dépliables sur « Tout », et les liste à plat sur un type', function () {
    $this->get('/')->assertOk()
        ->assertSee('<details', false)
        ->assertSeeInOrder(['Emplois', 'Home service', 'Formations']);

    $this->get('/services')->assertOk()->assertDontSee('<details', false);
});

it('range le compte connecté dans un menu déroulant', function () {
    $this->get('/')->assertSee('Se connecter')->assertDontSee('Menu du compte');

    $membre = Utilisateur::factory()->create(['prenoms' => 'Awa', 'nom' => 'Koné']);
    $this->actingAs($membre)->get('/')->assertOk()
        ->assertSee('Menu du compte')
        ->assertSee('aria-haspopup="true"', false)
        ->assertSee('Mes publications')
        ->assertSee('Se déconnecter')
        ->assertDontSee(route('admin.tableau-de-bord'));
});

it('remplace l’action de la carte par « Voir plus » vers la page détail', function () {
    $offre = Publication::factory()->emploi()->publiee()->create();

    $this->get('/')->assertOk()
        ->assertSeeText('Voir plus')
        ->assertDontSee('#repondre', false)
        ->assertDontSeeText('Postuler')
        ->assertSee('href="'.route('publications.show', $offre->slug).'"', false);
});

it('propose la commune dans une liste déroulante plutôt qu’en rangée de puces', function () {
    $html = (string) $this->get('/?commune=cocody')->assertOk()->getContent();

    expect($html)->toContain('id="choix-commune"')
        ->toContain('Toutes les communes')
        ->and(substr_count($html, '📍'))->toBe(1);
});

it('regroupe connexion et inscription dans le menu du visiteur', function () {
    $this->get('/')->assertOk()
        ->assertSee('aria-controls="menu-visiteur"', false)
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

it('affiche l’œil sur les mots de passe et l’indicatif court du pays', function () {
    $this->get(route('register'))->assertOk()
        ->assertSee('x-data="motDePasse"', false)
        ->assertSeeText('Afficher le mot de passe')
        ->assertSeeText('CI +225')
        ->assertDontSeeText('+225 — ');
});
