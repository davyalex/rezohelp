<?php

use App\Enums\CleDroit;
use App\Enums\StatutPublication;
use App\Enums\TypeArticle;
use App\Enums\TypePublication;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Commune;
use App\Models\DetailEmploi;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Factories\PaysFactory;
use Database\Seeders\ParametresSeeder;

it('relie une publication à son auteur, son entreprise, sa catégorie, sa commune et ses détails', function () {
    $entreprise = Entreprise::factory()->create();
    $categorie = Categorie::factory()->create();
    $commune = Commune::factory()->create();
    $publication = Publication::factory()->emploi()->create([
        'auteur_id' => $entreprise->proprietaire_id,
        'entreprise_id' => $entreprise->id,
        'categorie_id' => $categorie->id,
        'commune_id' => $commune->id,
    ]);
    DetailEmploi::factory()->create(['publication_id' => $publication->id]);

    $publication->load(['auteur', 'entreprise', 'categorie', 'commune', 'detailEmploi']);

    expect($publication->auteur?->is($entreprise->proprietaire))->toBeTrue()
        ->and($publication->entreprise?->is($entreprise))->toBeTrue()
        ->and($publication->categorie?->is($categorie))->toBeTrue()
        ->and($publication->commune?->is($commune))->toBeTrue()
        ->and($publication->detailEmploi?->publication_id)->toBe($publication->id);
});

it('relie un membre à ses publications, entreprises, alertes et enregistrements', function () {
    $membre = Utilisateur::factory()->create();
    $publication = Publication::factory()->create(['auteur_id' => $membre->id]);
    Entreprise::factory()->create(['proprietaire_id' => $membre->id]);
    Alerte::factory()->create(['utilisateur_id' => $membre->id]);
    $autre = Publication::factory()->publiee()->create();
    $membre->publicationsEnregistrees()->attach($autre->id, ['cree_le' => now()]);

    $membre->load(['publications', 'entreprises', 'alertes', 'publicationsEnregistrees', 'pays']);

    expect($membre->publications->modelKeys())->toBe([$publication->id])
        ->and($membre->entreprises)->toHaveCount(1)
        ->and($membre->alertes)->toHaveCount(1)
        ->and($membre->publicationsEnregistrees->modelKeys())->toBe([$autre->id])
        ->and($membre->pays?->code)->toBe('CI');
});

it('journalise les correspondances d’une alerte dans alertes_publications', function () {
    $alerte = Alerte::factory()->create();
    $publication = Publication::factory()->publiee()->create();

    $alerte->publications()->attach($publication->id, ['notifie_le' => now()]);

    expect($publication->alertes()->first()?->is($alerte))->toBeTrue()
        ->and($alerte->publications()->first()?->pivot?->getAttribute('notifie_le'))->not->toBeNull();
});

it('organise les catégories en arbre et les communes par pays', function () {
    $parent = Categorie::factory()->duType(TypePublication::Service)->create();
    $enfant = Categorie::factory()->duType(TypePublication::Service)->create(['parent_id' => $parent->id]);
    $commune = Commune::factory()->create();

    expect($parent->enfants()->first()?->is($enfant))->toBeTrue()
        ->and($enfant->parent?->is($parent))->toBeTrue()
        ->and($commune->pays?->is(PaysFactory::coteDivoire()))->toBeTrue();
});

it('stocke les titulaires polymorphes sous leur alias français', function () {
    // Un abonnement rafraîchit le badge des cartes, qui lit les paramètres (droits).
    $this->seed(ParametresSeeder::class);
    $membre = Utilisateur::factory()->create();
    $entreprise = Entreprise::factory()->create();
    $plan = Plan::factory()->create();

    $abonnement = Abonnement::factory()->create(['titulaire_type' => $membre->getMorphClass(), 'titulaire_id' => $membre->id, 'plan_id' => $plan->id]);
    $entreprise->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'actif', 'origine' => 'paiement']);

    expect($abonnement->titulaire_type)->toBe('utilisateur')
        ->and($abonnement->titulaire?->is($membre))->toBeTrue()
        ->and($membre->abonnements()->count())->toBe(1)
        ->and($entreprise->abonnements()->first()?->titulaire_type)->toBe('entreprise')
        ->and($plan->abonnements()->count())->toBe(2);
});

it('relie un plan à ses limites et une commande à son article', function () {
    $plan = Plan::factory()->create();
    PlanLimite::factory()->create(['plan_id' => $plan->id, 'cle' => CleDroit::AlertesCanalSms, 'valeur' => '1']);
    $produit = Produit::factory()->create();

    $commandePlan = Commande::factory()->create(['article_type' => TypeArticle::Plan, 'article_id' => $plan->id]);
    $commandeProduit = Commande::factory()->create(['article_type' => TypeArticle::Produit, 'article_id' => $produit->id]);

    expect($plan->limites()->first()?->cle)->toBe(CleDroit::AlertesCanalSms)
        ->and($commandePlan->plan?->is($plan))->toBeTrue()
        ->and($commandeProduit->produit?->is($produit))->toBeTrue()
        ->and($commandePlan->titulaire?->is($commandePlan->utilisateur))->toBeTrue();
});

it('filtre avec les scopes publiees, duType et dansCommune', function () {
    $commune = Commune::factory()->create();
    Publication::factory()->emploi()->publiee()->create(['commune_id' => $commune->id]);
    Publication::factory()->formation()->publiee()->create(['commune_id' => $commune->id]);
    Publication::factory()->emploi()->enAttente()->create(['commune_id' => $commune->id]);
    Publication::factory()->emploi()->publiee()->create();

    expect(Publication::query()->publiees()->count())->toBe(3)
        ->and(Publication::query()->publiees()->duType(TypePublication::Emploi)->count())->toBe(2)
        ->and(Publication::query()->publiees()->duType(TypePublication::Emploi)->dansCommune($commune->id)->count())->toBe(1)
        ->and(Publication::query()->where('statut', StatutPublication::EnAttente)->count())->toBe(1);
});
