<?php

use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\CodeOtp;
use App\Models\Commande;
use App\Models\Commentaire;
use App\Models\Commune;
use App\Models\Credit;
use App\Models\DetailEmploi;
use App\Models\DetailFormation;
use App\Models\DetailService;
use App\Models\Entreprise;
use App\Models\EssaiUtilise;
use App\Models\ImportVeille;
use App\Models\JournalActivite;
use App\Models\MiseEnAvant;
use App\Models\NotificationPaiement;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Reaction;
use App\Models\Recherche;
use App\Models\Reponse;
use App\Models\Signalement;
use App\Models\SmsEnvoye;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;

it('crée un modèle valide en base avec sa factory', function (string $classe) {
    /** @var class-string<Model> $classe */
    $modele = $classe::factory()->create();

    expect($modele->exists)->toBeTrue()
        ->and($classe::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    Abonnement::class,
    Alerte::class,
    AppelIa::class,
    Categorie::class,
    CodeOtp::class,
    Commande::class,
    Commentaire::class,
    Commune::class,
    Credit::class,
    DetailEmploi::class,
    DetailFormation::class,
    DetailService::class,
    Entreprise::class,
    EssaiUtilise::class,
    ImportVeille::class,
    JournalActivite::class,
    MiseEnAvant::class,
    NotificationPaiement::class,
    Parametre::class,
    Pays::class,
    Plan::class,
    PlanLimite::class,
    Produit::class,
    Publication::class,
    Reaction::class,
    Recherche::class,
    Reponse::class,
    Signalement::class,
    SmsEnvoye::class,
    SourceVeille::class,
    Utilisateur::class,
]);

it('déclare explicitement la table de chaque modèle', function (string $fichier) {
    $classe = 'App\\Models\\'.basename($fichier, '.php');
    $reflexion = new ReflectionClass($classe);

    expect($reflexion->getProperty('table')->getDeclaringClass()->getName())->toBe($classe);
})->with(glob(__DIR__.'/../../../app/Models/*.php') ?: []);

it('crée les états de publication attendus', function () {
    expect(Publication::factory()->serviceDemande()->create()->sous_type?->value)->toBe('demande')
        ->and(Publication::factory()->formation()->publiee()->create()->estPubliee())->toBeTrue()
        ->and(Publication::factory()->veille()->create()->source_empreinte)->toHaveLength(64)
        ->and(DetailService::factory()->demande()->create()->telephone_contact)->toBeNull();
});
