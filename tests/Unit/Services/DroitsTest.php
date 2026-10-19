<?php

use App\Enums\CleDroit;
use App\Enums\StatutPublication;
use App\Exceptions\QuotaAtteintException;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PlansSeeder;

beforeEach(function () {
    $this->seed([ParametresSeeder::class, PlansSeeder::class]);
    $this->membre = Utilisateur::factory()->create();
});

it('combine les plans par défaut des cibles du titulaire : maximum pour un entier, OU pour un booléen', function () {
    parametre('quotas_actifs', '1');
    $droits = Droits::pour($this->membre);

    // membre_gratuit (alertes 10, photos 2, réponses 3) + artisan_gratuit (offres actives 3, photos 4, réponses 5)
    expect($droits->limite(CleDroit::AlertesMax))->toBe(10)
        ->and($droits->limite(CleDroit::PublicationPhotosMax))->toBe(4)
        ->and($droits->limite(CleDroit::ReponsesDemandesMois))->toBe(5)
        ->and($droits->limite(CleDroit::ServiceOffresActives))->toBe(3)
        ->and($droits->limite(CleDroit::EmploiOffresMois))->toBe(1)
        ->and($droits->peut(CleDroit::AlertesCanalSms))->toBeFalse()
        ->and($droits->peut(CleDroit::ProfilBadge))->toBeFalse();
});

it('applique les plans recruteur et organisme à une entreprise', function () {
    parametre('quotas_actifs', '1');
    $droits = Droits::pour(Entreprise::factory()->create());

    expect($droits->limite(CleDroit::EmploiOffresMois))->toBe(3)
        ->and($droits->limite(CleDroit::FormationOffresActives))->toBe(1)
        ->and($droits->limite(CleDroit::AlertesMax))->toBe(0);
});

it('rend les quotas illimités quand quotas_actifs est désactivé, sauf les canaux et limites de taille', function () {
    $droits = Droits::pour($this->membre);

    expect($droits->limite(CleDroit::EmploiOffresMois))->toBe(CleDroit::ILLIMITE)
        ->and($droits->restant(CleDroit::ServiceOffresActives))->toBe(CleDroit::ILLIMITE)
        ->and($droits->peut(CleDroit::AlertesCanalSms))->toBeFalse()
        ->and($droits->limite(CleDroit::PublicationPhotosMax))->toBe(4)
        ->and($droits->limite(CleDroit::PublicationDureeMaxJours))->toBe(60);
});

it('fait l’emporter -1 (illimité) dans la combinaison', function () {
    parametre('quotas_actifs', '1');
    Plan::query()->where('code', 'artisan_gratuit')->firstOrFail()->limites()
        ->where('cle', CleDroit::ReponsesDemandesMois->value)->update(['valeur' => '-1']);

    expect(Droits::pour($this->membre)->limite(CleDroit::ReponsesDemandesMois))->toBe(CleDroit::ILLIMITE);
});

it('calcule la consommation par requête et lève QuotaAtteintException', function () {
    parametre('quotas_actifs', '1');
    Publication::factory()->count(2)->serviceOffre()->publiee()->create(['auteur_id' => $this->membre->id]);
    Publication::factory()->serviceOffre()->create(['auteur_id' => $this->membre->id]); // brouillon : ne compte pas
    $rejetee = Publication::factory()->serviceOffre()->create(['auteur_id' => $this->membre->id]);
    $rejetee->forceFill(['statut' => StatutPublication::Rejetee])->save();

    $droits = Droits::pour($this->membre);
    expect($droits->consommation(CleDroit::ServiceOffresActives))->toBe(2)
        ->and($droits->restant(CleDroit::ServiceOffresActives))->toBe(1);

    Publication::factory()->serviceOffre()->enAttente()->create(['auteur_id' => $this->membre->id]);
    Droits::pour($this->membre)->verifierOuEchouer(CleDroit::ServiceOffresActives);
})->throws(QuotaAtteintException::class);

it('compte les offres d’emploi du mois calendaire, hors rejetées et brouillons', function () {
    parametre('quotas_actifs', '1');
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id, 'cree_le' => now()->subMonth()]);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id]); // en son nom : compte pour le membre

    expect(Droits::pour($entreprise)->consommation(CleDroit::EmploiOffresMois))->toBe(1)
        ->and(Droits::pour($this->membre)->consommation(CleDroit::EmploiOffresMois))->toBe(1);
});
