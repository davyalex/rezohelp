<?php

use App\Enums\CibleOffre;
use App\Models\EssaiUtilise;
use App\Models\Plan;
use App\Models\Publication;
use App\Models\Reaction;
use App\Models\Utilisateur;
use Illuminate\Database\QueryException;

it('refuse deux publications avec la même empreinte source', function () {
    $empreinte = hash('sha256', 'https://www.emploi.ci/offre/123');
    Publication::factory()->create(['source_empreinte' => $empreinte]);

    Publication::factory()->create(['source_empreinte' => $empreinte]);
})->throws(QueryException::class);

it('accepte plusieurs publications natives sans empreinte source', function () {
    Publication::factory()->count(3)->create(['source_empreinte' => null]);

    expect(Publication::query()->whereNull('source_empreinte')->count())->toBe(3);
});

it('refuse deux comptes avec le même téléphone', function () {
    Utilisateur::factory()->create(['telephone' => '+2250708091011']);

    Utilisateur::factory()->create(['telephone' => '+2250708091011']);
})->throws(QueryException::class);

it('refuse deux publications avec le même code court', function () {
    Publication::factory()->create(['code_court' => 'ab12cd']);

    Publication::factory()->create(['code_court' => 'ab12cd']);
})->throws(QueryException::class);

it('refuse deux publications avec le même slug', function () {
    Publication::factory()->create(['slug' => 'comptable-confirme']);

    Publication::factory()->create(['slug' => 'comptable-confirme']);
})->throws(QueryException::class);

it('refuse une deuxième réaction du même membre sur une publication', function () {
    $reaction = Reaction::factory()->create();

    Reaction::factory()->create(['publication_id' => $reaction->publication_id, 'utilisateur_id' => $reaction->utilisateur_id]);
})->throws(QueryException::class);

it('refuse un deuxième essai pour la même empreinte et la même famille', function () {
    EssaiUtilise::factory()->create(['empreinte_telephone' => str_repeat('a', 64), 'famille' => 'alertes_sms']);

    EssaiUtilise::factory()->create(['empreinte_telephone' => str_repeat('a', 64), 'famille' => 'alertes_sms']);
})->throws(QueryException::class);

it('accepte un seul plan par défaut par cible', function () {
    Plan::factory()->parDefaut(CibleOffre::Membre)->create();

    Plan::factory()->parDefaut(CibleOffre::Membre)->create();
})->throws(QueryException::class);

it('accepte un plan par défaut pour chaque cible et plusieurs plans payants', function () {
    foreach (CibleOffre::cases() as $cible) {
        Plan::factory()->parDefaut($cible)->create();
        Plan::factory()->count(2)->create(['cible' => $cible]);
    }

    expect(Plan::query()->parDefaut()->count())->toBe(count(CibleOffre::cases()))
        ->and(Plan::query()->count())->toBe(3 * count(CibleOffre::cases()));
});
