<?php

use App\Enums\StatutCommande;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Commune;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Lot 10, parcours 4 : quota atteint → achat (passerelle simulée) → publication (dans un vrai navigateur).
 */
beforeEach(function () {
    referentielsPublication();
    parametre('quotas_actifs', '1');
    Produit::query()->where('code', 'offre_emploi_unite')->update(['actif' => true]);
});

it('achète une offre de plus quand le quota est atteint, puis publie', function () {
    $recruteur = Utilisateur::factory()->create();
    // Un particulier a droit à 1 offre d'emploi par mois dans le plan gratuit : elle est déjà publiée.
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $recruteur->id]);

    $this->actingAs($recruteur);
    $page = visit('/publier/emploi');

    $page->assertSee('Vous avez atteint votre limite')
        ->click('form[action$="/commandes"] button')
        ->assertSee('Guichet de paiement simulé')
        ->click('form:has(input[name="issue"][value="accepte"]) button')
        ->assertSee('Paiement confirmé');

    expect(Commande::query()->sole()->statut)->toBe(StatutCommande::Payee);

    $page->navigate('/publier/emploi')
        ->assertDontSee('Vous avez atteint votre limite')
        ->radio('employeur', 'particulier')
        ->select('categorie_id', (string) Categorie::query()->where('slug', 'emploi-finance-et-comptabilite')->value('id'))
        ->select('commune_id', (string) Commune::query()->where('slug', 'plateau')->value('id'))
        ->fill('titre', 'Assistante comptable au Plateau')
        ->fill('textarea[name="contenu"]', 'Saisie comptable, suivi des factures et rapprochements bancaires.')
        ->select('type_contrat', 'cdi');

    // Formulaire sans photo envoyé en urlencoded : voir PublicationArtisanTest.
    $page->script("(() => { const f = document.querySelector('form[enctype=\"multipart/form-data\"]'); f.enctype = 'application/x-www-form-urlencoded'; f.querySelectorAll('input[type=file]').forEach((c) => { c.disabled = true; }); return true; })()");

    $page->press('Publier')
        ->assertSee('Merci, votre')
        ->assertNoJavaScriptErrors();

    expect(Publication::query()->where('titre', 'Assistante comptable au Plateau')->exists())->toBeTrue();
});
