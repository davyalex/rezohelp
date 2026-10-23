<?php

use App\Models\Administrateur;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SourcesVeilleSeeder;

beforeEach(function () {
    referentielsPublication();
    $this->seed([SourcesVeilleSeeder::class, DemoSeeder::class]);
    $this->admin = Administrateur::factory()->administrateur()->create();
    $this->moderateur = Administrateur::factory()->moderateur()->create();
});

/** Pages et listes du back-office, avec la permission qui les ouvre. */
dataset('pages', [
    'tableau de bord' => ['admin.tableau-de-bord', true],
    'modération' => ['admin.moderation.index', true],
    'publications' => ['admin.publications.index', true],
    'signalements' => ['admin.signalements.index', true],
    'utilisateurs' => ['admin.utilisateurs.index', true],
    'entreprises' => ['admin.entreprises.index', true],
    'veille' => ['admin.veille.imports.index', false],
    'sources' => ['admin.veille.sources.index', false],
    'catégories' => ['admin.categories.index', false],
    'communes' => ['admin.communes.index', false],
    'pays' => ['admin.pays.index', false],
    'paramètres' => ['admin.parametres.edit', false],
    'plans' => ['admin.plans.index', false],
    'produits' => ['admin.produits.index', false],
    'abonnements' => ['admin.abonnements.index', false],
    'offrir' => ['admin.abonnements.create', false],
    'commandes' => ['admin.commandes.index', false],
    'revenus' => ['admin.revenus', false],
    'publicités' => ['admin.publicites.index', false],
    'nouvelle publicité' => ['admin.publicites.create', false],
]);

it('ouvre chaque page à l’administrateur, sans cache', function (string $route) {
    $reponse = $this->actingAs($this->admin, 'admin')->get(route($route))->assertOk();

    expect($reponse->headers->get('Cache-Control'))->toContain('no-store');
})->with('pages');

it('réserve au modérateur les seuls modules de son rôle', function (string $route, bool $autorise) {
    $this->actingAs($this->moderateur, 'admin')->get(route($route))->assertStatus($autorise ? 200 : 403);
})->with('pages');

it('renvoie visiteurs et membres du site vers la connexion de l’équipe', function () {
    $this->get(route('admin.tableau-de-bord'))->assertRedirect(route('admin.connexion'));
    // Une session membre n'ouvre pas le back-office : les comptes sont séparés (D-066).
    $this->actingAs(Utilisateur::factory()->create())->get(route('admin.tableau-de-bord'))->assertRedirect(route('admin.connexion'));
});

it('affiche les fiches de détail', function () {
    $enAttente = Publication::query()->where('statut', 'en_attente')->firstOrFail();
    $signalee = Signalement::query()->firstOrFail();
    $entreprise = Entreprise::query()->firstOrFail();

    $this->actingAs($this->admin, 'admin');
    $this->get(route('admin.moderation.show', $enAttente))->assertOk()->assertSee($enAttente->titre)->assertSee('Raccourcis');
    $this->get(route('admin.publications.edit', $enAttente))->assertOk();
    $this->get(route('admin.signalements.show', $signalee->publication_id))->assertOk();
    $this->get(route('admin.utilisateurs.show', $entreprise->proprietaire_id))->assertOk();
    $this->get(route('admin.utilisateurs.edit', $entreprise->proprietaire_id))->assertOk();
    $this->get(route('admin.entreprises.show', $entreprise))->assertOk();
    $this->get(route('admin.plans.edit', Plan::query()->firstOrFail()))->assertOk()->assertSee('alertes.max');
    $this->get(route('admin.plans.create'))->assertOk();
    $this->get(route('admin.produits.edit', Produit::query()->firstOrFail()))->assertOk();
    $this->get(route('admin.categories.create'))->assertOk();
    $this->get(route('admin.veille.sources.edit', SourceVeille::query()->firstOrFail()))->assertOk();
});

it('sert les listes DataTables en JSON côté serveur', function (string $route) {
    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route($route, ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

    expect(count($reponse->json('data')))->toBeLessThanOrEqual(10);
})->with([
    'admin.moderation.index', 'admin.publications.index', 'admin.signalements.index', 'admin.utilisateurs.index',
    'admin.entreprises.index', 'admin.veille.imports.index', 'admin.veille.sources.index', 'admin.communes.index',
    'admin.pays.index', 'admin.plans.index', 'admin.produits.index', 'admin.abonnements.index', 'admin.commandes.index', 'admin.publicites.index',
]);
