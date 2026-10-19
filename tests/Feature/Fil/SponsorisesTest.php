<?php

use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Models\Commune;
use App\Models\MiseEnAvant;
use App\Models\Publication;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    referentielsPublication();
    $this->base = Carbon::parse('2026-10-10 08:00:00');
    // 30 publications « organiques », de la plus récente à la plus ancienne.
    $this->organiques = collect(range(1, 30))
        ->map(fn (int $i) => Publication::factory()->publiee($this->base->copy()->subMinutes($i))->create());
});

/**
 * Cartes d'une réponse du fil, dans l'ordre : ['sponsorise' => bool, 'id' => int].
 *
 * @return list<array{sponsorise: bool, slug: string}>
 */
function cartesDuFil(TestResponse $reponse): array
{
    preg_match_all('#<li (data-carte|data-mise-en-avant="[^"]*")>.*?href="[^"]*/publication/([a-z0-9-]+)"#s', (string) $reponse->getContent(), $trouvees, PREG_SET_ORDER);

    return array_map(fn (array $carte): array => ['sponsorise' => $carte[1] !== 'data-carte', 'slug' => $carte[2]], $trouvees);
}

function sponsoriser(?Publication $publication = null, array $attributs = []): MiseEnAvant
{
    $publication ??= Publication::factory()->publiee(Carbon::parse('2026-01-01'))->create();

    return MiseEnAvant::factory()->create(['publication_id' => $publication->id, 'type' => TypeMiseEnAvant::SponsoriseFil, ...$attributs]);
}

it('injecte une carte sponsorisée en tête puis toutes les frequence_sponsorise cartes, hors curseur', function () {
    $sans = $this->get(route('fil.index'));
    $organiquesSans = array_column(cartesDuFil($sans), 'slug');

    collect(range(1, 3))->each(fn () => sponsoriser());
    $avec = $this->get(route('fil.index'))->assertOk()->assertSee('Sponsorisé');
    $cartes = cartesDuFil($avec);

    // Positions 0 et 8 de la salve de 12 (fréquence 8 par défaut).
    expect(array_keys(array_filter($cartes, fn (array $c): bool => $c['sponsorise'])))->toBe([0, 9])
        // La salve paginée est strictement identique : le curseur n'est pas touché.
        ->and(array_column(array_filter($cartes, fn (array $c): bool => ! $c['sponsorise']), 'slug'))->toBe($organiquesSans)
        ->and(count($organiquesSans))->toBe(12);

    // Salve suivante : 12 cartes déjà vues → prochain emplacement à la 16e carte (rang 4).
    $curseur = $avec->viewData('salve')->curseurSuivant;
    $suite = $this->get(route('fil.suite', ['curseur' => $curseur, 'deja' => 12]))->assertOk();
    $cartesSuite = cartesDuFil($suite);

    expect(array_keys(array_filter($cartesSuite, fn (array $c): bool => $c['sponsorise'])))->toBe([4])
        ->and($suite->headers->get('X-Curseur-Suivant'))->not->toBe('')
        ->and($suite->headers->get('Cache-Control'))->toContain('no-store');
});

it('n’affiche jamais deux fois la même carte sponsorisée dans une salve, ni une carte déjà dans la salve', function () {
    // Une seule mise en avant pour deux emplacements, et une autre dont la publication est déjà dans la salve.
    $unique = sponsoriser();
    sponsoriser($this->organiques[2]);

    $cartes = cartesDuFil($this->get(route('fil.index')));
    $sponsorisees = array_column(array_filter($cartes, fn (array $c): bool => $c['sponsorise']), 'slug');

    expect($sponsorisees)->toBe([$unique->publication->slug])
        ->and(array_count_values(array_column($cartes, 'slug'))[$this->organiques[2]->slug])->toBe(1);
});

it('fait tourner les cartes sponsorisées : la moins récemment affichée passe en premier', function () {
    $premiere = sponsoriser();
    $seconde = sponsoriser();
    parametre('frequence_sponsorise', '100');

    $vues = collect(range(1, 4))->map(function () {
        $this->travel(1)->minutes();

        return cartesDuFil($this->get(route('fil.index')))[0]['slug'];
    })->all();

    expect($vues)->toBe([$premiere->publication->slug, $seconde->publication->slug, $premiere->publication->slug, $seconde->publication->slug]);
});

it('ne met jamais en avant une publication hors ligne, une mise en avant suspendue ou terminée', function (string $cas) {
    $mise = sponsoriser();

    match ($cas) {
        'en attente' => $mise->publication->forceFill(['statut' => StatutPublication::EnAttente])->save(),
        'rejetée' => $mise->publication->forceFill(['statut' => StatutPublication::Rejetee])->save(),
        'expirée' => $mise->publication->forceFill(['statut' => StatutPublication::Expiree])->save(),
        'supprimée' => $mise->publication->delete(),
        'suspendue' => $mise->forceFill(['suspendue_le' => now()])->save(),
        'terminée' => $mise->update(['fin_le' => now()->subMinute()]),
    };

    $this->get(route('fil.index'))->assertOk()->assertDontSee('Sponsorisé');
})->with(['en attente', 'rejetée', 'expirée', 'supprimée', 'suspendue', 'terminée']);

it('réserve les cartes sponsorisées au fil général', function () {
    sponsoriser();
    $cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();

    $this->get(route('fil.index', ['commune' => $cocody->slug]))->assertDontSee('Sponsorisé');
    $this->get(route('fil.index', ['q' => 'comptable']))->assertDontSee('Sponsorisé');
    $this->get(route('fil.emplois'))->assertSee('Sponsorisé');
});

it('affiche le bloc « À la une » au-dessus d’un fil filtré sur la commune, sans répéter la carte', function () {
    parametre('max_cartes_une', '2');
    $cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $yopougon = Commune::query()->where('slug', 'yopougon')->firstOrFail();
    $publications = collect(range(1, 3))->map(fn (int $i) => Publication::factory()->publiee($this->base->copy()->addMinutes($i))->create(['commune_id' => $cocody->id]));
    foreach ($publications as $publication) {
        MiseEnAvant::factory()->create(['publication_id' => $publication->id, 'type' => TypeMiseEnAvant::UneCommune, 'commune_id' => $cocody->id]);
    }
    $ailleurs = Publication::factory()->publiee()->create(['commune_id' => $yopougon->id]);
    MiseEnAvant::factory()->create(['publication_id' => $ailleurs->id, 'type' => TypeMiseEnAvant::UneCommune, 'commune_id' => $yopougon->id]);

    $reponse = $this->get(route('fil.index', ['commune' => 'cocody']))->assertOk()->assertSee('À la une');
    $cartes = cartesDuFil($reponse);
    $aLaUne = array_column(array_filter($cartes, fn (array $c): bool => $c['sponsorise']), 'slug');

    expect($aLaUne)->toHaveCount(2)
        ->and(array_slice(array_column($cartes, 'sponsorise'), 0, 2))->toBe([true, true])
        ->and($aLaUne)->not->toContain($ailleurs->slug)
        // Pas de doublon entre le bloc et la liste.
        ->and(max(array_count_values(array_column($cartes, 'slug'))))->toBe(1);

    $this->get(route('fil.index'))->assertDontSee('À la une');
});

it('suspend la mise en avant quand la publication sort du fil et rend la durée restante à son retour', function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
    $mise = sponsoriser(null, ['debut_le' => now()->subDays(2), 'fin_le' => now()->addDays(5)]);

    $mise->publication->forceFill(['statut' => StatutPublication::EnAttente])->save();
    expect($mise->refresh()->suspendue_le?->equalTo(now()))->toBeTrue();

    $this->travel(3)->days();
    $mise->publication->forceFill(['statut' => StatutPublication::Publiee])->save();

    $mise->refresh();
    expect($mise->suspendue_le)->toBeNull()
        ->and($mise->fin_le?->equalTo(now()->addDays(5)))->toBeTrue();
});

it('compte les impressions et les clics en cache puis les reporte en base', function () {
    $mise = sponsoriser();
    parametre('frequence_sponsorise', '100');

    $this->get(route('fil.index'));
    $this->get(route('fil.index'));
    $this->post(route('mises-en-avant.clic', $mise))->assertNoContent();

    expect($mise->refresh()->nb_impressions)->toBe(0);

    $this->artisan('mises-en-avant:compter')->assertSuccessful();
    expect($mise->refresh()->nb_impressions)->toBe(2)->and($mise->nb_clics)->toBe(1);

    // Les compteurs sont remis à zéro après le report.
    $this->artisan('mises-en-avant:compter');
    expect($mise->refresh()->nb_impressions)->toBe(2);
});

it('ignore les clics sur une mise en avant terminée', function () {
    $mise = sponsoriser(null, ['fin_le' => now()->subDay()]);

    $this->post(route('mises-en-avant.clic', $mise))->assertNoContent();
    $this->artisan('mises-en-avant:compter');

    expect($mise->refresh()->nb_clics)->toBe(0);
});
