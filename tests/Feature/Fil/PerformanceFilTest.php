<?php

use App\Models\Commentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Lot 9 : le nombre de requêtes du fil ne dépend pas du nombre de cartes (aucun N+1),
 * pour un visiteur comme pour un membre (états « j'aime » et « enregistré » en une requête).
 */
beforeEach(fn () => referentielsPublication());

function requetesPour(Closure $appel): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $appel();
    $nombre = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $nombre;
}

it('garde le même nombre de requêtes sur le fil avec 2 ou 12 cartes', function (?string $connecte) {
    $membre = $connecte !== null ? Utilisateur::factory()->create() : null;
    $visiter = function () use ($membre) {
        $requete = $membre !== null ? $this->actingAs($membre) : $this;
        $requete->get('/')->assertOk();
    };

    Publication::factory()->count(2)->publiee()->create();
    $avecDeux = requetesPour($visiter);

    Publication::factory()->count(10)->publiee()->create();
    $avecDouze = requetesPour($visiter);

    expect($avecDouze)->toBeLessThanOrEqual($avecDeux)
        ->and($avecDouze)->toBeLessThanOrEqual(30);
})->with(['visiteur' => [null], 'membre' => ['membre']]);

it('garde le même nombre de requêtes sur la suite du fil', function () {
    Publication::factory()->count(14)->publiee()->create();
    $curseur = $this->get('/fil/suite')->headers->get('X-Curseur-Suivant');

    Publication::factory()->count(24)->publiee(now()->subYear())->create();
    $deuxiemeSalve = requetesPour(fn () => $this->get('/fil/suite?curseur='.$curseur)->assertOk());
    $suivante = $this->get('/fil/suite?curseur='.$curseur)->headers->get('X-Curseur-Suivant');
    $troisiemeSalve = requetesPour(fn () => $this->get('/fil/suite?curseur='.$suivante)->assertOk());

    expect($troisiemeSalve)->toBeLessThanOrEqual($deuxiemeSalve);
});

it('ne charge la page détail qu’en un nombre fixe de requêtes, quel que soit le nombre de commentaires', function () {
    $publication = Publication::factory()->emploi()->publiee()->create();
    Commentaire::factory()->count(2)->create(['publication_id' => $publication->id]);
    $avecDeux = requetesPour(fn () => $this->get(route('publications.show', $publication->slug))->assertOk());

    Commentaire::factory()->count(15)->create(['publication_id' => $publication->id]);
    $avecDixSept = requetesPour(fn () => $this->get(route('publications.show', $publication->slug))->assertOk());

    expect($avecDixSept)->toBeLessThanOrEqual($avecDeux);
});
