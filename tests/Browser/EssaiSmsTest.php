<?php

use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Utilisateur;

/**
 * Lot 10, parcours 5 : essai des alertes SMS → fin de l'essai → achat du pass (dans un vrai navigateur).
 */
beforeEach(function () {
    referentielsPublication();
    fauxSms();
});

it('essaie les alertes SMS, voit l’essai se terminer, puis achète le pass', function () {
    $membre = Utilisateur::factory()->create(['telephone_verifie_le' => now()]);

    $this->actingAs($membre);
    $page = visit('/mon-espace/alertes/creer');

    // L'incitation « Recevez-la aussi par SMS » s'affiche juste après la création d'une alerte (§14.11).
    $page->select('type', 'service')
        ->fill('nom', 'Plombier à Cocody')
        ->press('Créer l’alerte')
        ->assertSee('Recevez-la aussi par SMS')
        ->click('form[action$="/essais"] button')
        ->assertNoJavaScriptErrors();

    $essai = Abonnement::query()->sole();
    expect($essai->statut)->toBe(StatutAbonnement::Essai);

    // Fin de l'essai (14 jours) : la tâche horaire le fait expirer, sans rien débiter.
    $this->travel(15)->days();
    $this->artisan('abonnements:traiter')->assertSuccessful();
    expect($essai->refresh()->statut)->toBe(StatutAbonnement::Expire);

    $page->navigate('/mon-espace/abonnement')
        ->assertDontSee('Essayer 14 jours gratuits')
        ->click('form[action$="/commandes"]:has(input[name="code"][value="alertes_sms_30j"]) button')
        ->assertSee('Guichet de paiement simulé')
        ->click('form:has(input[name="issue"][value="accepte"]) button')
        ->assertSee('Paiement confirmé')
        ->assertNoJavaScriptErrors();

    $pass = Abonnement::query()->where('statut', StatutAbonnement::Actif->value)->sole();
    expect($pass->plan->code)->toBe('alertes_sms_30j')
        ->and($pass->fin_le?->isAfter(now()->addDays(29)))->toBeTrue();
});
