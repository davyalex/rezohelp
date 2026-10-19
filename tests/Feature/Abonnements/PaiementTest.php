<?php

use App\Contracts\PasserellePaiement;
use App\Enums\CleDroit;
use App\Enums\StatutAbonnement;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiementPasserelle;
use App\Enums\TypeArticle;
use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\NotificationPaiement;
use App\Models\Plan;
use App\Models\Utilisateur;
use App\Notifications\CommandePayee;
use App\Services\Droits;
use App\Services\Paiement\FakePasserelle;
use App\Support\Montant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    referentielsPublication();
    Notification::fake();
    $this->membre = Utilisateur::factory()->create();
    $this->plan = Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail();
});

function commander(Utilisateur $membre, string $code, string $type = 'plan', array $champs = []): Commande
{
    test()->actingAs($membre)
        ->post(route('commandes.store'), ['article_type' => $type, 'code' => $code, ...$champs])
        ->assertRedirect();

    return Commande::query()->latest('id')->firstOrFail();
}

/**
 * Simule l'issue côté passerelle puis envoie sa notification signée au webhook.
 */
function notifierPaiement(Commande $commande, StatutPaiementPasserelle $statut = StatutPaiementPasserelle::Accepte, ?int $montant = null, ?string $signature = null): TestResponse
{
    $passerelle = app(PasserellePaiement::class);
    assert($passerelle instanceof FakePasserelle);
    $passerelle->simuler((string) $commande->reference_passerelle, $statut, $montant);

    $corps = (string) json_encode(['reference' => $commande->reference_passerelle]);

    return test()->call('POST', route('paiements.notification', 'fake'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature ?? $passerelle->signer($corps),
    ], $corps);
}

it('fige le montant de la commande avec la TVA et ouvre le guichet', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    expect($commande->numero)->toMatch('/^CMD-\d{4}-000001$/')
        ->and($commande->statut)->toBe(StatutCommande::EnAttente)
        ->and($commande->article_type)->toBe(TypeArticle::Plan)
        ->and($commande->montant_ttc_fcfa)->toBe(1000)
        ->and($commande->montant_ht_fcfa)->toBe(847)
        ->and($commande->reference_passerelle)->toBe($commande->numero);

    // Un changement de prix après la commande ne change pas le montant dû.
    $this->plan->update(['prix_fcfa' => 2000]);
    expect($commande->refresh()->montant_ttc_fcfa)->toBe(1000);
});

it('refuse de commander un plan inactif, gratuit ou d’une autre cible', function (string $code) {
    $this->actingAs($this->membre)
        ->from(route('espace.abonnement'))
        ->post(route('commandes.store'), ['article_type' => 'plan', 'code' => $code])
        ->assertRedirect(route('espace.abonnement'))
        ->assertSessionHas('erreur');

    expect(Commande::query()->count())->toBe(0);
})->with(['entreprise_30j', 'membre_gratuit', 'organisme_pro_30j']);

it('active la commande uniquement au webhook revérifié et numérote le reçu', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    // La page de retour seule n'active rien.
    $this->get(route('paiements.retour', $commande))->assertOk()->assertSee('Paiement en cours de confirmation');
    expect($commande->refresh()->statut)->toBe(StatutCommande::EnAttente);

    notifierPaiement($commande)->assertOk();

    $commande->refresh();
    expect($commande->statut)->toBe(StatutCommande::Payee)
        ->and($commande->numero_recu)->toMatch('/^REC-\d{4}-000001$/')
        ->and($commande->payee_le)->not->toBeNull()
        ->and(Droits::pour($this->membre)->peut(CleDroit::AlertesCanalSms))->toBeTrue();

    $abonnement = Abonnement::query()->sole();
    expect($abonnement->statut)->toBe(StatutAbonnement::Actif)
        ->and($abonnement->commande_id)->toBe($commande->id)
        ->and($abonnement->fin_le?->toDateString())->toBe(now()->addDays(30)->toDateString());

    Notification::assertSentTo($this->membre, CommandePayee::class);
    expect(NotificationPaiement::query()->sole()->traitee_le)->not->toBeNull();

    $this->get(route('paiements.retour', $commande))->assertSee('Paiement confirmé')->assertSee((string) $commande->numero_recu);
});

it('reste idempotent quand la notification est rejouée', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    notifierPaiement($commande)->assertOk();
    notifierPaiement($commande)->assertOk();
    notifierPaiement($commande)->assertOk();

    expect(Abonnement::query()->count())->toBe(1)
        ->and(NotificationPaiement::query()->count())->toBe(3)
        ->and(Commande::query()->whereNotNull('numero_recu')->count())->toBe(1);
    Notification::assertSentToTimes($this->membre, CommandePayee::class, 1);
});

it('rejette une notification à la signature invalide sans rien activer', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    notifierPaiement($commande, signature: 'falsifiee')->assertForbidden();

    $journal = NotificationPaiement::query()->sole();
    expect($journal->signature_valide)->toBeFalse()
        ->and($journal->erreur)->toBe('Signature invalide.')
        ->and($commande->refresh()->statut)->toBe(StatutCommande::EnAttente)
        ->and(Abonnement::query()->count())->toBe(0);
});

it('n’active jamais un paiement dont le montant ne correspond pas', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    notifierPaiement($commande, montant: 10)->assertOk();

    expect($commande->refresh()->statut)->toBe(StatutCommande::Echouee)
        ->and($commande->donnees_passerelle['anomalie'] ?? null)->toContain('Montant payé')
        ->and(Abonnement::query()->count())->toBe(0);
});

it('marque la commande échouée quand la passerelle refuse le paiement', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    notifierPaiement($commande, StatutPaiementPasserelle::Refuse)->assertOk();

    expect($commande->refresh()->statut)->toBe(StatutCommande::Echouee)
        ->and(Abonnement::query()->count())->toBe(0);
    $this->get(route('paiements.retour', $commande))->assertSee('Paiement non abouti');
});

it('n’accepte les notifications que du driver configuré', function () {
    $this->post(route('paiements.notification', 'cinetpay'))->assertNotFound();
    $this->get(route('paiements.notification', 'fake'))->assertOk();
});

it('ajoute un renouvellement à la suite de la période en cours', function () {
    notifierPaiement(commander($this->membre, 'alertes_sms_30j'))->assertOk();
    notifierPaiement(commander($this->membre, 'alertes_sms_30j'))->assertOk();

    [$premier, $second] = Abonnement::query()->orderBy('id')->get()->all();

    expect($second->debut_le?->equalTo($premier->fin_le))->toBeTrue()
        ->and($second->fin_le?->toDateString())->toBe(now()->addDays(60)->toDateString())
        ->and(Commande::query()->orderBy('id')->pluck('numero_recu')->all())
        ->toBe(['REC-'.now()->format('Y').'-000001', 'REC-'.now()->format('Y').'-000002']);
});

it('remplace un essai en cours par le plan payé, qui démarre tout de suite', function () {
    $essai = Abonnement::factory()->essai()->create([
        'titulaire_id' => $this->membre->id, 'plan_id' => $this->plan->id, 'debut_le' => now()->subDays(3), 'fin_le' => now()->addDays(11),
    ]);

    notifierPaiement(commander($this->membre, 'alertes_sms_30j'))->assertOk();

    $paye = Abonnement::query()->whereKeyNot($essai->id)->sole();
    expect($essai->refresh()->statut)->toBe(StatutAbonnement::Expire)
        ->and($paye->debut_le?->isToday())->toBeTrue()
        ->and($paye->statut)->toBe(StatutAbonnement::Actif);
});

it('propose le guichet simulé du driver fake, qui passe par le même traitement', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');

    $this->get(route('paiements.retour', $commande))->assertSee('Simuler un paiement réussi');
    $this->post(route('paiements.simuler', $commande), ['issue' => 'accepte'])->assertRedirect(route('paiements.retour', $commande));

    expect($commande->refresh()->statut)->toBe(StatutCommande::Payee);
    $this->post(route('paiements.simuler', $commande), ['issue' => 'accepte'])->assertNotFound();
});

it('réserve la page de retour et le reçu à l’acheteur', function () {
    $commande = commander($this->membre, 'alertes_sms_30j');
    $autre = Utilisateur::factory()->create();

    $this->actingAs($autre)->get(route('paiements.retour', $commande))->assertForbidden();
    $this->actingAs($autre)->post(route('paiements.simuler', $commande), ['issue' => 'accepte'])->assertForbidden();

    // Pas de reçu tant que la commande n'est pas payée.
    $this->actingAs($this->membre)->get(route('espace.recu', $commande))->assertForbidden();

    notifierPaiement($commande);
    $this->actingAs($autre)->get(route('espace.recu', $commande))->assertForbidden();
});

it('affiche le reçu avec les mentions légales et le fournit en PDF', function () {
    parametre('mentions_legales_recu', '<p onclick="alert(1)">Resohelph SARL — RCCM CI-ABJ-2026-B-1</p><script>alert(1)</script>');
    $commande = commander($this->membre, 'alertes_sms_30j');
    notifierPaiement($commande);
    $commande->refresh();

    $this->actingAs($this->membre)->get(route('espace.recu', $commande))
        ->assertOk()
        ->assertSee((string) $commande->numero_recu)
        ->assertSee('Resohelph SARL — RCCM CI-ABJ-2026-B-1')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('onclick', false)
        ->assertSee(Montant::fcfa(1000))
        ->assertSee(Montant::fcfa(847));

    $pdf = $this->get(route('espace.recu.pdf', $commande))->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toContain('recu-'.$commande->numero_recu.'.pdf');
});
