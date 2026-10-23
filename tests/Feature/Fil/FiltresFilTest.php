<?php

use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Publication;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PaysSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([PaysSeeder::class, CommunesSeeder::class, CategoriesSeeder::class, ParametresSeeder::class]);
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $this->yopougon = Commune::query()->where('slug', 'yopougon')->firstOrFail();
    $this->batiment = Categorie::query()->where('slug', 'service-batiment-et-reparations')->firstOrFail();
    $this->plomberie = Categorie::query()->where('slug', 'service-plomberie')->firstOrFail();
});

/**
 * Titres des cartes affichées par une page du fil.
 *
 * @return list<string>
 */
function titresAffiches(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();
    preg_match_all('/<h2 id="carte-\d+-titre"[^>]*>\s*<a [^>]*>([^<]+)<\/a>/', (string) $html, $resultats);

    return array_map(fn (string $titre): string => html_entity_decode(trim($titre)), $resultats[1]);
}

it('filtre par type sur les onglets', function () {
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Comptable']);
    Publication::factory()->serviceOffre()->publiee()->create(['titre' => 'Plombier']);
    Publication::factory()->formation()->publiee()->create(['titre' => 'Bootcamp']);

    expect(titresAffiches('/'))->toHaveCount(3)
        ->and(titresAffiches('/emplois'))->toBe(['Comptable'])
        ->and(titresAffiches('/services'))->toBe(['Plombier'])
        ->and(titresAffiches('/formations'))->toBe(['Bootcamp']);
});

it('filtre par commune, contrat, catégorie (avec ses métiers) et « aujourd’hui », combinés', function () {
    Publication::factory()->emploi()->publiee(now()->subMinutes(5))->create([
        'titre' => 'CDI Cocody du jour', 'commune_id' => $this->cocody->id, 'type_contrat' => TypeContrat::Cdi,
    ]);
    Publication::factory()->emploi()->publiee(now()->subDays(2))->create([
        'titre' => 'CDI Cocody ancien', 'commune_id' => $this->cocody->id, 'type_contrat' => TypeContrat::Cdi,
    ]);
    Publication::factory()->emploi()->publiee(now()->subMinutes(5))->create([
        'titre' => 'Stage Cocody du jour', 'commune_id' => $this->cocody->id, 'type_contrat' => TypeContrat::Stage,
    ]);
    Publication::factory()->emploi()->publiee(now()->subMinutes(5))->create([
        'titre' => 'CDI Yopougon du jour', 'commune_id' => $this->yopougon->id, 'type_contrat' => TypeContrat::Cdi,
    ]);
    Publication::factory()->serviceOffre()->publiee()->create([
        'titre' => 'Plombier Cocody', 'commune_id' => $this->cocody->id, 'categorie_id' => $this->plomberie->id,
    ]);

    expect(titresAffiches('/emplois?commune=cocody'))->toEqualCanonicalizing(['CDI Cocody du jour', 'CDI Cocody ancien', 'Stage Cocody du jour'])
        ->and(titresAffiches('/emplois?commune=cocody&contrat=cdi'))->toEqualCanonicalizing(['CDI Cocody du jour', 'CDI Cocody ancien'])
        ->and(titresAffiches('/emplois?commune=cocody&contrat=cdi&aujourdhui=1'))->toBe(['CDI Cocody du jour'])
        ->and(titresAffiches('/?aujourdhui=1&contrat=cdi'))->toEqualCanonicalizing(['CDI Cocody du jour', 'CDI Yopougon du jour'])
        // Une catégorie principale inclut ses sous-catégories (métiers).
        ->and(titresAffiches('/services?categorie=service-batiment-et-reparations'))->toBe(['Plombier Cocody'])
        ->and(titresAffiches('/services?categorie=service-plomberie&commune=yopougon'))->toBe([]);
});

it('ignore un filtre inconnu au lieu de casser la page', function () {
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Comptable']);

    expect(titresAffiches('/emplois?commune=inconnue&contrat=nimporte&categorie=xyz'))->toBe(['Comptable']);
});

it('affiche un état vide avec un bouton pour retirer les filtres', function () {
    $this->get('/emplois?commune=cocody')
        ->assertOk()
        ->assertSee('Aucune publication ne correspond pour le moment.')
        ->assertSee('Retirer les filtres');
});

it('conserve la commune en changeant d’onglet et marque l’onglet courant', function () {
    $html = $this->get('/emplois?commune=cocody')->assertOk()->getContent();

    expect($html)->toContain('href="'.route('fil.services', ['commune' => 'cocody']).'"')
        ->toContain('aria-current="page"');
});

it('rejette une recherche trop longue', function () {
    $this->get('/recherche?q='.str_repeat('a', 101))->assertRedirect();
});

it('ne lit jamais les tables de détails pour afficher le fil', function () {
    Publication::factory()->count(3)->emploi()->publiee()->create();

    DB::enableQueryLog();
    $this->get('/')->assertOk();
    $requetes = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect($requetes)->not->toContain('details_emploi')
        ->not->toContain('details_service')
        ->not->toContain('details_formation');
});

it('rend la première salve côté serveur avec la zone aria-live et le lien de secours', function () {
    Publication::factory()->count(14)->publiee()->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('aria-live="polite"', false)
        ->assertSee('Charger plus')
        ->assertSee('data-curseur="ey', false);
});

it('affiche « Vous êtes à jour » quand tout est chargé', function () {
    Publication::factory()->count(2)->publiee()->create();

    $this->get('/')->assertOk()->assertSee('Vous êtes à jour')->assertDontSee('Charger plus');
});

it('accepte le type d’un onglet en paramètre pour les fragments', function () {
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Comptable']);
    Publication::factory()->formation()->publiee()->create(['titre' => 'Bootcamp']);

    $html = (string) $this->get('/fil/suite?type='.TypePublication::Formation->value)->assertOk()->getContent();

    expect($html)->toContain('Bootcamp')->not->toContain('Comptable');
});

it('annonce le filtre actif par aria-current : aria-pressed est réservé aux boutons', function () {
    Publication::factory()->count(2)->emploi()->publiee()->create();

    $html = (string) $this->get('/emplois?contrat=cdi&aujourdhui=1')->assertOk()->getContent();

    // Les deux filtres actifs (CDI, Aujourd'hui) sont annoncés ; aucun lien ne porte aria-pressed.
    expect(preg_match('/<a\s[^>]*aria-pressed/', $html))->toBe(0)
        ->and(substr_count($html, 'aria-current="true"'))->toBeGreaterThanOrEqual(2);
});
