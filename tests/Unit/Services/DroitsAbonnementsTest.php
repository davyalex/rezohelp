<?php

use App\Enums\CleDroit;
use App\Enums\StatutAbonnement;
use App\Enums\StatutPublication;
use App\Models\Abonnement;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PlansSeeder;

beforeEach(function () {
    $this->seed([ParametresSeeder::class, PlansSeeder::class]);
    parametre('quotas_actifs', '1');
    $this->membre = Utilisateur::factory()->create();
});

function abonner(Utilisateur|Entreprise $titulaire, string $code, StatutAbonnement $statut = StatutAbonnement::Actif, array $attributs = []): Abonnement
{
    return Abonnement::factory()->create(array_merge([
        'titulaire_type' => $titulaire->getMorphClass(),
        'titulaire_id' => $titulaire->getKey(),
        'plan_id' => Plan::query()->where('code', $code)->firstOrFail()->id,
        'statut' => $statut,
    ], $attributs));
}

it('ajoute les droits des abonnements essai, actif et grâce au plan par défaut', function (StatutAbonnement $statut) {
    abonner($this->membre, 'artisan_pro_30j', $statut);

    $droits = Droits::pour($this->membre);
    expect($droits->limite(CleDroit::ServiceOffresActives))->toBe(10)
        ->and($droits->limite(CleDroit::ReponsesDemandesMois))->toBe(CleDroit::ILLIMITE)
        ->and($droits->peut(CleDroit::ProfilBadge))->toBeTrue()
        // Les autres clés gardent le plan par défaut.
        ->and($droits->limite(CleDroit::AlertesMax))->toBe(10);
})->with([StatutAbonnement::Essai, StatutAbonnement::Actif, StatutAbonnement::Grace]);

it('ignore les abonnements expirés, résiliés ou pas encore commencés', function () {
    abonner($this->membre, 'alertes_sms_30j', StatutAbonnement::Expire);
    abonner($this->membre, 'alertes_sms_30j', StatutAbonnement::Resilie);
    abonner($this->membre, 'alertes_sms_30j', StatutAbonnement::Actif, ['debut_le' => now()->addDays(2), 'fin_le' => now()->addDays(32)]);

    expect(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeFalse();
});

it('combine plusieurs abonnements simultanés', function () {
    abonner($this->membre, 'alertes_sms_7j');
    abonner($this->membre, 'artisan_pro_30j');

    $droits = Droits::pour($this->membre);
    expect($droits->peut(CleDroit::AlertesCanalSms))->toBeTrue()
        ->and($droits->limite(CleDroit::AlertesSmsParJour))->toBe(5)
        ->and($droits->limite(CleDroit::PublicationPhotosMax))->toBe(10);
});

it('recalcule les droits dès qu’un abonnement ou une limite change (cache invalidé)', function () {
    expect(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeFalse();

    $abonnement = abonner($this->membre, 'alertes_sms_30j');
    expect(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeTrue();

    PlanLimite::query()->where('plan_id', $abonnement->plan_id)->where('cle', CleDroit::AlertesSmsParJour->value)->firstOrFail()->update(['valeur' => '9']);
    expect(Droits::pour($this->membre)->limite(CleDroit::AlertesSmsParJour))->toBe(9);

    $abonnement->update(['statut' => StatutAbonnement::Expire]);
    expect(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeFalse();
});

it('ajoute les crédits non consommés et non expirés au reste', function () {
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    Publication::factory()->emploi()->publiee()->count(3)->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);
    $credit = Credit::factory()->create([
        'titulaire_type' => 'entreprise', 'titulaire_id' => $entreprise->id, 'cle' => CleDroit::EmploiOffresMois,
        'quantite' => 1, 'consommee' => 0, 'expire_le' => now()->addDays(30),
    ]);
    Credit::factory()->create([
        'titulaire_type' => 'entreprise', 'titulaire_id' => $entreprise->id, 'cle' => CleDroit::EmploiOffresMois,
        'quantite' => 5, 'consommee' => 0, 'expire_le' => now()->subDay(),
    ]);

    $droits = Droits::pour($entreprise);
    expect($droits->restant(CleDroit::EmploiOffresMois))->toBe(1);

    // Une 4e offre dépasse le plan : le crédit est consommé.
    Publication::factory()->emploi()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id, 'statut' => StatutPublication::EnAttente]);
    $droits->imputer(CleDroit::EmploiOffresMois);

    expect($credit->refresh()->consommee)->toBe(1)
        ->and(Droits::pour($entreprise)->restant(CleDroit::EmploiOffresMois))->toBe(0);
});

it('ne consomme aucun crédit tant que le plan suffit', function () {
    $credit = Credit::factory()->create([
        'titulaire_type' => 'utilisateur', 'titulaire_id' => $this->membre->id, 'cle' => CleDroit::EmploiOffresMois,
        'quantite' => 1, 'consommee' => 0, 'expire_le' => null,
    ]);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id]);

    Droits::pour($this->membre)->imputer(CleDroit::EmploiOffresMois);

    expect($credit->refresh()->consommee)->toBe(0);
});

it('rend les crédits inutiles quand les quotas sont désactivés', function () {
    parametre('quotas_actifs', '0');

    expect(Droits::pour($this->membre)->restant(CleDroit::EmploiOffresMois))->toBe(CleDroit::ILLIMITE);
});
