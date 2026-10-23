<?php

use App\Enums\EmplacementPublicite;
use App\Models\Publication;
use App\Models\Publicite;
use App\Models\Utilisateur;
use App\Services\Publicites;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PaysSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([PaysSeeder::class, CommunesSeeder::class, CategoriesSeeder::class, ParametresSeeder::class]);
});

it('prépare « Annoncez ici » dans les emplacements verticaux libres, et rien dans les bannières libres', function () {
    Publication::factory()->emploi()->publiee()->count(10)->create();
    $html = (string) $this->get(route('fil.index'))->assertOk()->getContent();

    expect($html)->toContain('data-publicite="marge_gauche"')
        ->toContain('data-publicite="marge_droite"')
        ->toContain('data-publicite="colonne_fil"')
        ->toContain('Annoncez ici')
        ->toContain('href="'.route('tarifs').'"')
        ->not->toContain('data-publicite="fil_entre_cartes"')
        // Le contenu reste avant les marges dans le code (lecteurs d'écran, clavier).
        ->and(strpos($html, 'id="contenu"'))->toBeLessThan(strpos($html, 'data-publicite="marge_gauche"'));
});

it('n’affiche aucune publicité sur les formulaires ni dans « Mon espace »', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('data-publicite', false);
    $this->get(route('register'))->assertOk()->assertDontSee('data-publicite', false);
    $this->actingAs(Utilisateur::factory()->create())->get(route('espace.index'))->assertOk()->assertDontSee('data-publicite', false);
});

it('place une bannière après la 8e carte de chaque salve et sous l’annonce d’une offre', function () {
    Publicite::factory()->emplacement(EmplacementPublicite::FilEntreCartes)->avecImage()->create(['annonceur' => 'Banque Atlantique']);
    Publicite::factory()->emplacement(EmplacementPublicite::DetailBas)->avecImage()->create(['annonceur' => 'École des métiers']);
    $publications = Publication::factory()->emploi()->publiee()->count(20)->create();

    $fil = (string) $this->get(route('fil.index'))->assertOk()->getContent();
    expect(substr_count($fil, 'data-publicite="fil_entre_cartes"'))->toBe(1)
        ->and($fil)->toContain('Banque Atlantique');

    // La salve suivante (fil infini) a aussi la sienne.
    $curseur = (string) str($fil)->match('/data-curseur="([^"]+)"/');
    $this->get(route('fil.suite', ['curseur' => $curseur]))->assertOk()->assertSee('data-publicite="fil_entre_cartes"', false);

    $this->get(route('publications.show', $publications->first()->slug))->assertOk()
        ->assertSee('data-publicite="detail_bas"', false)
        ->assertSee('École des métiers');
});

it('diffuse une annonce seulement pendant sa période et si elle est active', function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $finie = Publicite::factory()->avecImage()->create(['fin_le' => '2026-11-10']);
    $future = Publicite::factory()->avecImage()->create(['debut_le' => '2026-12-01']);
    $inactive = Publicite::factory()->avecImage()->create(['active' => false]);
    $enCours = Publicite::factory()->avecImage()->create(['debut_le' => '2026-11-01', 'fin_le' => '2026-11-30']);

    $service = app(Publicites::class);
    foreach (range(1, 10) as $essai) {
        expect($service->annonce(EmplacementPublicite::MargeGauche)['id'])->toBe($enCours->id);
    }
    expect($service->annonce(EmplacementPublicite::MargeDroite))->toBeNull();
});

it('affiche une annonce libellée, sponsorisée, avec son image secondaire pour le bon écran', function () {
    $publicite = Publicite::factory()->avecImage()->create(['annonceur' => 'Orange CI', 'texte_alternatif' => 'Orange : forfaits internet']);

    $html = (string) $this->get(route('fil.index'))->assertOk()->getContent();

    expect($html)->toContain('data-publicite-id="'.$publicite->id.'"')
        ->toContain('href="'.route('publicites.clic', $publicite).'" target="_blank" rel="sponsored noopener"')
        ->toContain('alt="Orange : forfaits internet" width="160" height="600"')
        ->toContain('Orange CI')
        ->toContain('>Publicité<');
});

it('compte le clic et redirige vers l’annonceur, puis reporte les compteurs en base', function () {
    $publicite = Publicite::factory()->avecImage()->create(['lien' => 'https://annonceur.ci/offre']);

    $this->get(route('publicites.clic', $publicite))->assertRedirect('https://annonceur.ci/offre');
    $this->post(route('publicites.vues'), ['ids' => [$publicite->id, 999999]])->assertNoContent();
    $this->post(route('publicites.vues'), ['ids' => [$publicite->id]])->assertNoContent();

    $this->artisan('publicites:compter')->assertSuccessful();

    expect($publicite->refresh())->nb_clics->toBe(1)->nb_impressions->toBe(2);
});

it('renvoie « Annoncez ici » vers le contact réglé, et coupe tout depuis l’admin', function () {
    parametre('publicite_contact', 'mailto:pub@rezohelp.ci');
    $this->get(route('fil.index'))->assertSee('href="mailto:pub@rezohelp.ci"', false);

    Publicite::factory()->avecImage()->create();
    parametre('publicites_actives', '0');
    $this->get(route('fil.index'))->assertOk()->assertDontSee('data-publicite', false);
});

it('n’utilise jamais un lien « Annoncez ici » dangereux ou invalide', function (string $lien) {
    parametre('publicite_contact', $lien);

    $this->get(route('fil.index'))->assertOk()
        ->assertSee('href="'.route('tarifs').'"', false)
        ->assertDontSee($lien, false);
})->with(['javascript:alert(1)', 'data:text/html,x', 'pub@rezohelp.ci']);
