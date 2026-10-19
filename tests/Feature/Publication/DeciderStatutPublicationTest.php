<?php

use App\Actions\Publication\DeciderStatutPublication;
use App\Enums\StatutPublication;
use App\Exceptions\PublicationImpossibleException;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\ParametresSeeder;

/*
 * Chaque ligne du tableau §11.6.
 */

beforeEach(function () {
    $this->seed(ParametresSeeder::class);
    $this->decider = app(DeciderStatutPublication::class);
});

function auteurAvecHistorique(string $confiance = 'nouveau'): Utilisateur
{
    $auteur = Utilisateur::factory()->create(['confiance' => $confiance]);
    Publication::factory()->publiee()->create(['auteur_id' => $auteur->id]);

    return $auteur;
}

it('met en attente la première publication du compte, même fiable ou entreprise vérifiée', function () {
    $fiable = Utilisateur::factory()->fiable()->create();
    $entreprise = Entreprise::factory()->verifiee()->create(['proprietaire_id' => $fiable->id]);

    expect($this->decider->executer($fiable, $entreprise, []))->toBe(StatutPublication::EnAttente);
});

it('ne compte pas les brouillons comme publications précédentes', function () {
    $auteur = Utilisateur::factory()->fiable()->create();
    Publication::factory()->create(['auteur_id' => $auteur->id]);

    expect($this->decider->executer($auteur, null, []))->toBe(StatutPublication::EnAttente);
});

it('met en attente dès qu’un signal de risque est présent', function () {
    $auteur = auteurAvecHistorique('fiable');
    $entreprise = Entreprise::factory()->verifiee()->create(['proprietaire_id' => $auteur->id]);

    expect($this->decider->executer($auteur, $entreprise, ['expression_a_risque']))->toBe(StatutPublication::EnAttente);
});

it('publie immédiatement pour un auteur fiable dont le score atteint le seuil', function () {
    $auteur = auteurAvecHistorique('fiable');

    expect($this->decider->executer($auteur, null, [], 70))->toBe(StatutPublication::Publiee)
        ->and($this->decider->executer($auteur, null, [], 69))->toBe(StatutPublication::EnAttente);
});

it('décide sans le score tant qu’il n’est pas calculé : un auteur fiable est publié', function () {
    expect($this->decider->executer(auteurAvecHistorique('fiable'), null, [], null))->toBe(StatutPublication::Publiee);
});

it('publie immédiatement pour une entreprise vérifiée sans signal', function () {
    $auteur = auteurAvecHistorique();
    $entreprise = Entreprise::factory()->verifiee()->create(['proprietaire_id' => $auteur->id]);

    expect($this->decider->executer($auteur, $entreprise, []))->toBe(StatutPublication::Publiee);
});

it('met en attente dans tous les autres cas', function () {
    $auteur = auteurAvecHistorique();
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $auteur->id]);

    expect($this->decider->executer($auteur, null, []))->toBe(StatutPublication::EnAttente)
        ->and($this->decider->executer($auteur, $entreprise, []))->toBe(StatutPublication::EnAttente);
});

it('refuse la publication pour un auteur restreint', function () {
    $this->decider->executer(auteurAvecHistorique('restreint'), null, []);
})->throws(PublicationImpossibleException::class);

it('suit le seuil de publication automatique réglé dans les paramètres', function () {
    parametre('seuil_publication_auto', '90');

    expect($this->decider->executer(auteurAvecHistorique('fiable'), null, [], 85))->toBe(StatutPublication::EnAttente);
});
