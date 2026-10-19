<?php

use App\Actions\Abonnement\DemarrerEssai;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Entreprise;
use App\Models\MiseEnAvant;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Support\Montant;

beforeEach(function () {
    referentielsPublication();
    $this->membre = Utilisateur::factory()->create();
});

it('génère /tarifs à partir des plans visibles et actifs', function () {
    Plan::query()->where('code', 'alertes_sms_7j')->update(['visible' => false]);

    $this->get(route('tarifs'))
        ->assertOk()
        ->assertSee('Alertes SMS 30 jours')
        ->assertSee(Montant::fcfa(1000))
        ->assertSee('14 jours d’essai gratuit')
        ->assertDontSee('Alertes SMS 7 jours')
        // Plans payants inactifs au lancement (§14.12).
        ->assertDontSee('Artisan Pro')
        ->assertSee('Remontée');
});

it('montre la formule en cours, les compteurs et l’historique dans « Mon abonnement »', function () {
    parametre('quotas_actifs', '1');
    Alerte::factory()->count(2)->create(['utilisateur_id' => $this->membre->id, 'active' => true]);
    app(DemarrerEssai::class)->executer($this->membre, $this->membre, Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail());
    Entreprise::factory()->create(['proprietaire_id' => $this->membre->id, 'nom' => 'Batiplus']);

    $this->actingAs($this->membre)->get(route('espace.abonnement'))
        ->assertOk()
        ->assertSee('Alertes SMS 30 jours')
        ->assertSee('Essai')
        ->assertSee('2 / 10')
        ->assertSee('SMS d’alerte par jour')
        ->assertSee('Batiplus')
        ->assertSee('Aucune commande pour le moment.')
        ->assertSee('Renouveler');
});

it('propose l’essai SMS juste après la création d’une alerte, puis plus une fois utilisé', function () {
    $commune = Commune::query()->where('slug', 'cocody')->firstOrFail();

    $this->actingAs($this->membre)
        ->followingRedirects()
        ->post(route('alertes.store'), ['commune_id' => $commune->id, 'frequence' => 'instantanee'])
        ->assertSee('Recevez-la aussi par SMS — 14 jours gratuits');

    $this->get(route('alertes.index'))->assertDontSee('Recevez-la aussi par SMS');

    app(DemarrerEssai::class)->executer($this->membre, $this->membre, Plan::query()->where('code', 'alertes_sms_7j')->firstOrFail());
    $this->followingRedirects()
        ->post(route('alertes.store'), ['commune_id' => $commune->id, 'type' => 'emploi', 'frequence' => 'instantanee'])
        ->assertDontSee('Recevez-la aussi par SMS');
});

it('affiche les options d’achat et d’essai sur l’écran « Quota atteint »', function () {
    parametre('quotas_actifs', '1');
    Produit::query()->where('code', 'offre_emploi_unite')->update(['actif' => true]);
    Plan::query()->where('code', 'entreprise_30j')->update(['actif' => true, 'visible' => true]);
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id, 'nom' => 'Batiplus']);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id]);
    Publication::factory()->emploi()->publiee()->count(3)->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);

    $this->actingAs($this->membre)->get(route('publier.formulaire', 'emploi'))
        ->assertOk()
        ->assertSee('Vous avez atteint votre limite')
        ->assertSee('Publier 1 offre de plus · '.Montant::fcfa(3000))
        ->assertSee('Essayer « Entreprise » 14 jours gratuits')
        ->assertSee('Batiplus');
});

it('prévient quand il ne reste qu’une offre gratuite ce mois-ci', function () {
    parametre('quotas_actifs', '1');
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    Publication::factory()->emploi()->publiee()->count(2)->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);
    Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->get(route('publier.formulaire', 'emploi'))
        ->assertOk()
        ->assertSee('Il vous reste 1 offre d’emploi gratuite ce mois-ci.');
});

it('propose la remontée et les mises en avant dans « Mes publications » avec leurs statistiques', function () {
    Produit::query()->update(['actif' => true]);
    $publication = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->membre->id]);
    MiseEnAvant::factory()->create(['publication_id' => $publication->id, 'nb_impressions' => 120, 'nb_clics' => 7]);

    $this->actingAs($this->membre)->get(route('espace.publications.index'))
        ->assertOk()
        ->assertSee('Remonter en tête du fil · '.Montant::fcfa(500))
        ->assertSee('À la une 7 j')
        ->assertSee('Sponsoriser 7 j')
        ->assertSee('120 affichages · 7 clics')
        ->assertDontSee('Statistiques détaillées');
});

it('affiche les statistiques avancées et la remontée incluse avec une formule Pro', function () {
    Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->membre->id, 'nb_vues' => 200, 'nb_reponses' => 10]);
    Abonnement::factory()->create(['titulaire_id' => $this->membre->id, 'plan_id' => Plan::query()->where('code', 'artisan_pro_30j')->firstOrFail()->id]);

    $this->actingAs($this->membre)->get(route('espace.publications.index'))
        ->assertSee('Statistiques détaillées', false)
        ->assertSee('Taux de réponse : 5 %')
        ->assertSee('(incluse, 4 restante(s))');
});

it('affiche le badge Pro sur les cartes de l’abonné', function () {
    $categorie = Categorie::query()->where('type', 'service')->firstOrFail();
    $publication = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->membre->id, 'categorie_id' => $categorie->id]);

    Abonnement::factory()->create(['titulaire_id' => $this->membre->id, 'plan_id' => Plan::query()->where('code', 'artisan_pro_30j')->firstOrFail()->id]);

    expect($publication->refresh()->auteur_badge)->toBe('pro');
    $this->get(route('fil.index'))->assertSee('>Pro<', false);
});
