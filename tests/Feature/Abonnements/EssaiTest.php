<?php

use App\Actions\Abonnement\DemarrerEssai;
use App\Actions\Compte\AnonymiserUtilisateur;
use App\Enums\CleDroit;
use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\EssaiUtilise;
use App\Models\Plan;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\Offres;
use App\Services\TelephoneService;

beforeEach(function () {
    referentielsPublication();
    $this->membre = Utilisateur::factory()->create(['telephone' => '+2250708091011']);
    $this->sms7 = Plan::query()->where('code', 'alertes_sms_7j')->firstOrFail();
    $this->sms30 = Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail();
});

it('démarre un essai en un clic, sans paiement, et ouvre les droits du plan', function () {
    $this->actingAs($this->membre)
        ->post(route('essais.store'), ['plan' => 'alertes_sms_30j'])
        ->assertRedirect(route('espace.abonnement'))
        ->assertSessionHas('statut');

    $abonnement = Abonnement::query()->sole();
    expect($abonnement->statut)->toBe(StatutAbonnement::Essai)
        ->and($abonnement->origine)->toBe(OrigineAbonnement::Essai)
        ->and($abonnement->fin_le?->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeTrue()
        ->and(EssaiUtilise::query()->sole()->empreinte_telephone)->toBe(hash('sha256', '+2250708091011'));
});

it('n’accorde qu’un essai par famille de plans', function () {
    app(DemarrerEssai::class)->executer($this->membre, $this->membre, $this->sms7);

    expect(fn () => app(DemarrerEssai::class)->executer($this->membre, $this->membre, $this->sms30))
        ->toThrow(OperationImpossibleException::class, 'déjà été utilisé');
});

it('refuse un second essai au même numéro, même après la suppression du compte', function () {
    app(DemarrerEssai::class)->executer($this->membre, $this->membre, $this->sms30);
    app(AnonymiserUtilisateur::class)->executer($this->membre);

    $nouveauCompte = Utilisateur::factory()->create(['telephone' => '+2250708091011']);

    expect(fn () => app(DemarrerEssai::class)->executer($nouveauCompte, $nouveauCompte, $this->sms30))
        ->toThrow(OperationImpossibleException::class, 'déjà été utilisé');
    // Le numéro n'est jamais conservé en clair.
    expect(EssaiUtilise::query()->where('empreinte_telephone', '+2250708091011')->exists())->toBeFalse();
});

it('exige un numéro vérifié', function () {
    $nonVerifie = Utilisateur::factory()->nonVerifie()->create();

    $this->actingAs($nonVerifie)
        ->from(route('alertes.index'))
        ->post(route('essais.store'), ['plan' => 'alertes_sms_30j'])
        ->assertRedirect(route('alertes.index'))
        ->assertSessionHas('erreur', 'Vérifiez d’abord votre numéro de téléphone pour démarrer un essai.');

    expect(Abonnement::query()->count())->toBe(0);
});

it('refuse un plan sans essai ou inactif', function () {
    $sansEssai = Plan::factory()->create(['cible' => 'membre', 'duree_essai_jours' => 0]);
    $inactif = Plan::factory()->avecEssai(14, 'test')->create(['cible' => 'membre', 'actif' => false]);

    foreach ([$sansEssai, $inactif] as $plan) {
        expect(fn () => app(DemarrerEssai::class)->executer($this->membre, $this->membre, $plan))
            ->toThrow(OperationImpossibleException::class, 'ne propose pas d’essai');
    }
});

it('compte l’essai d’une entreprise sur le numéro de son propriétaire', function () {
    $plan = Plan::query()->where('code', 'entreprise_30j')->firstOrFail();
    $plan->update(['actif' => true]);
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    $autreEntreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);

    $this->actingAs($this->membre)
        ->post(route('essais.store'), ['plan' => 'entreprise_30j', 'entreprise_id' => $entreprise->id])
        ->assertRedirect(route('espace.abonnement'));

    expect(Abonnement::query()->sole()->titulaire_type)->toBe('entreprise')
        ->and(fn () => app(DemarrerEssai::class)->executer($this->membre, $autreEntreprise, $plan))
        ->toThrow(OperationImpossibleException::class, 'déjà été utilisé');
});

it('refuse l’essai pour l’entreprise d’un autre membre', function () {
    $entreprise = Entreprise::factory()->create();

    $this->actingAs($this->membre)
        ->post(route('essais.store'), ['plan' => 'alertes_sms_30j', 'entreprise_id' => $entreprise->id])
        ->assertSessionHasErrors('entreprise_id');
});

it('ne propose plus l’essai une fois utilisé', function () {
    expect(app(Offres::class)->essaiPour($this->membre, $this->membre, CleDroit::AlertesCanalSms)?->famille)->toBe('alertes_sms');

    app(DemarrerEssai::class)->executer($this->membre, $this->membre, $this->sms7);

    expect(app(Offres::class)->essaiPour($this->membre, $this->membre, CleDroit::AlertesCanalSms))->toBeNull()
        ->and(app(TelephoneService::class)->empreinte('+2250708091011'))->toBe(hash('sha256', '+2250708091011'));
});
