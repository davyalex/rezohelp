<?php

use App\Actions\Commande\ActiverCommande;
use App\Actions\Commande\CreerCommande;
use App\Contracts\PasserellePaiement;
use App\Enums\CleDroit;
use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiementPasserelle;
use App\Models\Abonnement;
use App\Models\Administrateur;
use App\Models\Commande;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\JournalActivite;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\Paiement\FakePasserelle;
use App\Services\StatistiquesService;
use App\Support\Montant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    referentielsPublication();
    Notification::fake();
    $this->admin = Administrateur::factory()->administrateur()->create();
    $this->artisan = Utilisateur::factory()->create();
});

/**
 * Champs du formulaire de plan à partir d'un plan existant, avec ses limites actuelles.
 *
 * @return array<string, mixed>
 */
function formulairePlan(Plan $plan, array $surcharge = []): array
{
    // Comme le formulaire : « illimité » est une case à cocher, le nombre reste vide.
    $limites = [];
    $illimite = [];
    foreach ($plan->limites as $limite) {
        $champ = str_replace('.', '__', $limite->cle->value);
        $limite->valeur === '-1' ? $illimite[$champ] = '1' : $limites[$champ] = $limite->valeur;
    }

    return array_merge([
        'code' => $plan->code, 'nom' => $plan->nom, 'description' => $plan->description, 'cible' => $plan->cible->value,
        'famille' => $plan->famille, 'prix_fcfa' => $plan->prix_fcfa, 'duree_jours' => $plan->duree_jours,
        'duree_essai_jours' => $plan->duree_essai_jours, 'est_defaut' => $plan->est_defaut ? '1' : '0',
        'actif' => $plan->actif ? '1' : '0', 'visible' => $plan->visible ? '1' : '0', 'ordre' => $plan->ordre,
        'limites' => $limites, 'illimite' => $illimite,
    ], $surcharge);
}

it('modifie le prix et une limite : effet immédiat sur les droits et la page tarifs', function () {
    parametre('quotas_actifs', '1');
    $plan = Plan::query()->where('code', 'artisan_gratuit')->with('limites')->firstOrFail();
    expect(Droits::pour($this->artisan)->limite(CleDroit::ServiceOffresActives))->toBe(3);

    $donnees = formulairePlan($plan);
    $donnees['limites']['service__offres_actives'] = '7';
    $this->actingAs($this->admin, 'admin')->put(route('admin.plans.update', $plan), $donnees)->assertRedirect(route('admin.plans.edit', $plan));

    expect(Droits::pour($this->artisan)->limite(CleDroit::ServiceOffresActives))->toBe(7);

    $sms = Plan::query()->where('code', 'alertes_sms_30j')->with('limites')->firstOrFail();
    $this->put(route('admin.plans.update', $sms), formulairePlan($sms, ['prix_fcfa' => 1200]));
    $this->get(route('tarifs'))->assertSee(Montant::fcfa(1200));

    $journal = JournalActivite::query()->where('action', 'plan.modifie')->where('sujet_id', $plan->id)->sole();
    expect($journal->donnees['limites_avant']['service.offres_actives'])->toBe('3')
        ->and($journal->donnees['limites_apres']['service.offres_actives'])->toBe('7');
});

it('enregistre « illimité » et retire une limite laissée vide', function () {
    $plan = Plan::query()->where('code', 'artisan_pro_30j')->with('limites')->firstOrFail();
    $donnees = formulairePlan($plan);
    $donnees['illimite']['service__offres_actives'] = '1';
    unset($donnees['limites']['profil__badge']);

    $this->actingAs($this->admin, 'admin')->put(route('admin.plans.update', $plan), $donnees)->assertRedirect()->assertSessionHasNoErrors();

    $limites = $plan->limites()->pluck('valeur', 'cle');
    expect($limites['service.offres_actives'])->toBe('-1')->and($limites->has('profil.badge'))->toBeFalse();
});

it('crée un plan et garde un seul plan par défaut par cible', function () {
    parametre('quotas_actifs', '1');
    $ancien = Plan::query()->where('code', 'membre_gratuit')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->post(route('admin.plans.store'), [
        'code' => 'membre_gratuit_v2', 'nom' => 'Gratuit v2', 'cible' => 'membre', 'prix_fcfa' => 0, 'duree_jours' => 0, 'duree_essai_jours' => 0,
        'est_defaut' => '1', 'actif' => '1', 'visible' => '1', 'ordre' => 1, 'limites' => ['alertes__max' => '12'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($ancien->refresh()->est_defaut)->toBeFalse()
        ->and(Plan::query()->where('cible', 'membre')->where('est_defaut', true)->value('code'))->toBe('membre_gratuit_v2')
        ->and(Droits::pour($this->artisan)->limite(CleDroit::AlertesMax))->toBe(12);
});

it('refuse un plan par défaut payant ou un essai sans famille', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.plans.store'), [
        'code' => 'incoherent', 'nom' => 'Incohérent', 'cible' => 'artisan', 'prix_fcfa' => 500, 'duree_jours' => 30, 'duree_essai_jours' => 7,
        'est_defaut' => '1', 'ordre' => 1,
    ])->assertSessionHasErrors(['prix_fcfa', 'famille']);
});

it('interdit de supprimer un plan souscrit ou par défaut, autorise un plan jamais souscrit', function () {
    $souscrit = Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail();
    Abonnement::factory()->create(['titulaire_id' => $this->artisan->id, 'plan_id' => $souscrit->id, 'statut' => StatutAbonnement::Expire]);

    $this->actingAs($this->admin, 'admin')->delete(route('admin.plans.destroy', $souscrit))
        ->assertSessionHas('erreur', 'Ce plan a déjà été souscrit : désactivez-le plutôt que de le supprimer.');
    $this->delete(route('admin.plans.destroy', Plan::query()->where('code', 'membre_gratuit')->firstOrFail()))->assertSessionHas('erreur');

    $libre = Plan::query()->where('code', 'organisme_pro_30j')->firstOrFail();
    $this->delete(route('admin.plans.destroy', $libre))->assertRedirect(route('admin.plans.index'));

    expect(Plan::query()->whereKey($souscrit->id)->exists())->toBeTrue()
        ->and(Plan::query()->whereKey($libre->id)->exists())->toBeFalse()
        ->and(JournalActivite::query()->where('action', 'plan.supprime')->exists())->toBeTrue();
});

it('offre un abonnement à un artisan : droits immédiats, origine et motif conservés', function () {
    $plan = Plan::query()->where('code', 'artisan_pro_30j')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->post(route('admin.abonnements.store'), [
        'titulaire' => 'utilisateur', 'utilisateur_id' => $this->artisan->id, 'plan_id' => $plan->id, 'jours' => 60, 'motif' => 'Partenaire de lancement',
    ])->assertSessionHas('statut');

    $abonnement = Abonnement::query()->sole();
    expect($abonnement->origine)->toBe(OrigineAbonnement::OffertAdmin)
        ->and($abonnement->motif)->toBe('Partenaire de lancement')
        ->and($abonnement->fin_le?->toDateString())->toBe(now()->addDays(60)->toDateString())
        ->and(Droits::pour($this->artisan)->peut(CleDroit::ProfilBadge))->toBeTrue()
        ->and(JournalActivite::query()->where('action', 'abonnement.offert')->exists())->toBeTrue();
});

it('refuse d’offrir un plan d’une autre cible', function () {
    $entreprise = Entreprise::factory()->create();

    $this->actingAs($this->admin, 'admin')->post(route('admin.abonnements.store'), [
        'titulaire' => 'entreprise', 'entreprise_id' => $entreprise->id, 'plan_id' => Plan::query()->where('code', 'artisan_pro_30j')->value('id'), 'jours' => 30, 'motif' => 'Test',
    ])->assertSessionHas('erreur');

    expect(Abonnement::query()->count())->toBe(0);
});

it('prolonge puis résilie un abonnement', function () {
    $abonnement = Abonnement::factory()->create([
        'titulaire_id' => $this->artisan->id, 'plan_id' => Plan::query()->where('code', 'artisan_pro_30j')->value('id'),
        'statut' => StatutAbonnement::Grace, 'fin_le' => now()->subDay(), 'relances_envoyees' => ['j3', 'j1', 'j0'],
    ]);

    $this->actingAs($this->admin, 'admin')->post(route('admin.abonnements.prolonger', $abonnement), ['jours' => 15]);
    $abonnement->refresh();
    expect($abonnement->statut)->toBe(StatutAbonnement::Actif)
        ->and($abonnement->fin_le?->toDateString())->toBe(now()->addDays(15)->toDateString())
        ->and($abonnement->relances_envoyees)->toBeNull();

    $this->post(route('admin.abonnements.resilier', $abonnement));
    expect($abonnement->refresh()->statut)->toBe(StatutAbonnement::Resilie)
        ->and(Droits::pour($this->artisan)->peut(CleDroit::ProfilBadge))->toBeFalse()
        ->and(JournalActivite::query()->whereIn('action', ['abonnement.prolonge', 'abonnement.resilie'])->count())->toBe(2);
});

it('revérifie une commande en attente auprès de la passerelle, de façon idempotente', function () {
    $commande = app(CreerCommande::class)->executer($this->artisan, $this->artisan, Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail());
    $commande->forceFill(['reference_passerelle' => $commande->numero])->save();
    $passerelle = app(PasserellePaiement::class);
    assert($passerelle instanceof FakePasserelle);
    $passerelle->simuler($commande->numero, StatutPaiementPasserelle::Accepte, 1000);

    $this->actingAs($this->admin, 'admin')->post(route('admin.commandes.reverifier', $commande))->assertSessionHas('statut');
    $this->post(route('admin.commandes.reverifier', $commande));

    expect($commande->refresh()->statut)->toBe(StatutCommande::Payee)
        ->and(Abonnement::query()->count())->toBe(1)
        ->and(JournalActivite::query()->where('action', 'commande.reverifiee')->count())->toBe(2);
});

it('rembourse une commande : abonnement résilié, crédits et mises en avant arrêtés', function () {
    $passerelle = app(PasserellePaiement::class);
    assert($passerelle instanceof FakePasserelle);
    Produit::query()->update(['actif' => true]);

    $commande = app(CreerCommande::class)->executer($this->artisan, $this->artisan, Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail());
    app(ActiverCommande::class)->executer($commande, 'wave');
    $offre = app(CreerCommande::class)->executer($this->artisan, $this->artisan, Produit::query()->where('code', 'offre_emploi_unite')->firstOrFail());
    app(ActiverCommande::class)->executer($offre, 'wave');

    $this->actingAs($this->admin, 'admin')->post(route('admin.commandes.rembourser', $commande), ['motif' => 'Erreur de plan'])->assertSessionHas('statut');
    $this->post(route('admin.commandes.rembourser', $offre), ['motif' => 'Doublon de paiement']);

    expect($commande->refresh()->statut)->toBe(StatutCommande::Remboursee)
        ->and(Abonnement::query()->sole()->statut)->toBe(StatutAbonnement::Resilie)
        ->and(Credit::query()->sole()->consommee)->toBe(1)
        ->and(JournalActivite::query()->where('action', 'commande.remboursee')->count())->toBe(2);

    // Une commande non payée ne se rembourse pas.
    $this->post(route('admin.commandes.rembourser', $commande), ['motif' => 'Encore'])->assertSessionHas('erreur');
    // Le reçu reste téléchargeable par l'équipe, depuis le back-office (D-066).
    $this->get(route('admin.commandes.recu-pdf', $commande))->assertOk()->assertDownload('recu-'.$commande->numero_recu.'.pdf');
});

it('exporte les commandes filtrées en CSV', function () {
    $commande = app(CreerCommande::class)->executer($this->artisan, $this->artisan, Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail());

    $reponse = $this->actingAs($this->admin, 'admin')->get(route('admin.commandes.export', ['statut' => 'en_attente']))->assertOk();
    $csv = $reponse->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Commande;Date;Acheteur')
        ->and($csv)->toContain($commande->numero);
    expect($this->get(route('admin.commandes.export', ['statut' => 'payee']))->streamedContent())->not->toContain($commande->numero);
});

it('calcule les revenus du mois et la conversion des essais', function () {
    $plan = Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail();
    Abonnement::factory()->essai()->create(['titulaire_id' => $this->artisan->id, 'plan_id' => $plan->id, 'statut' => StatutAbonnement::Expire]);
    $commande = app(CreerCommande::class)->executer($this->artisan, $this->artisan, $plan);
    app(ActiverCommande::class)->executer($commande, 'orange');

    $revenus = app(StatistiquesService::class)->revenus();

    expect($revenus['ca_mois'])->toBe(1000)
        ->and($revenus['ca_plans'])->toBe(1000)
        ->and($revenus['essais_termines'])->toBe(1)
        ->and($revenus['essais_convertis'])->toBe(1)
        ->and($revenus['actifs_par_plan'])->toBe(['Alertes SMS 30 jours' => 1]);
    $this->actingAs($this->admin, 'admin')->get(route('admin.revenus'))->assertOk()->assertSee('100 %');
});
