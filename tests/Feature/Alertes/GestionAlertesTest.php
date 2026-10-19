<?php

use App\Enums\FrequenceAlerte;
use App\Enums\TypePublication;
use App\Models\Alerte;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\PlanLimite;
use App\Models\Utilisateur;

beforeEach(function () {
    referentielsPublication();
    $this->membre = Utilisateur::factory()->create();
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $this->electricite = Categorie::query()->where('slug', 'service-electricite')->firstOrFail();
});

it('crée une alerte et la nomme d’après ses critères', function () {
    $this->actingAs($this->membre)->post('/mon-espace/alertes', [
        'type' => 'service', 'categorie_id' => $this->electricite->id, 'commune_id' => $this->cocody->id, 'frequence' => 'instantanee',
    ])->assertRedirect(route('alertes.index'));

    $alerte = Alerte::query()->sole();
    expect($alerte->utilisateur_id)->toBe($this->membre->id)
        ->and($alerte->nom)->toBe('Électricité · Cocody')
        ->and($alerte->active)->toBeTrue()
        ->and($alerte->frequence)->toBe(FrequenceAlerte::Instantanee);

    $this->get('/mon-espace/alertes')->assertSee('Électricité · Cocody')->assertSee('Voir les résultats');
});

it('exige au moins un critère et un contrat seulement pour un emploi', function () {
    $this->actingAs($this->membre)->post('/mon-espace/alertes', ['frequence' => 'instantanee'])->assertSessionHasErrors('type');
    $this->post('/mon-espace/alertes', ['type' => 'service', 'type_contrat' => 'cdi', 'frequence' => 'instantanee'])->assertSessionHasErrors('type_contrat');
    $this->post('/mon-espace/alertes', ['type' => 'emploi', 'categorie_id' => $this->electricite->id, 'frequence' => 'instantanee'])->assertSessionHasErrors('categorie_id');

    expect(Alerte::query()->count())->toBe(0);
});

it('pré-remplit le formulaire avec les filtres du fil', function () {
    $this->actingAs($this->membre)
        ->get('/mon-espace/alertes/creer?type=service&commune=cocody&categorie=service-electricite&q=installation')
        ->assertOk()
        ->assertSee('<option value="'.$this->cocody->id.'" selected>', false)
        ->assertSee('<option value="'.$this->electricite->id.'" selected>', false)
        ->assertSee('value="installation"', false);
});

it('envoie un visiteur vers l’inscription puis le ramène à l’alerte pré-remplie', function () {
    fauxSms();

    $this->get('/alertes/nouvelle?type=service&commune=cocody&categorie=service-electricite')->assertRedirect(route('register'));

    $this->post('/inscription', [
        'prenoms' => 'Awa', 'nom' => 'Koné', 'pays_code' => 'CI', 'telephone' => '05 06 07 08 09',
        'password' => 'Abidjan-Cocody-2026', 'password_confirmation' => 'Abidjan-Cocody-2026',
    ])->assertRedirect(route('telephone.verification'));

    $cible = session('url.intended');
    expect($cible)->toContain('/mon-espace/alertes/creer')->toContain('commune=cocody');

    $this->get($cible)->assertOk()->assertSee('<option value="'.$this->cocody->id.'" selected>', false);
});

it('mène directement un membre connecté au formulaire depuis le fil', function () {
    $this->actingAs($this->membre)->get('/alertes/nouvelle?commune=cocody')->assertRedirect(route('alertes.create', ['commune' => 'cocody']));
});

it('limite le nombre d’alertes actives à alertes.max quand les quotas sont actifs', function () {
    parametre('quotas_actifs', '1');
    PlanLimite::query()->where('cle', 'alertes.max')->update(['valeur' => '2']);
    Alerte::factory()->count(2)->create(['utilisateur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->get('/mon-espace/alertes/creer')->assertOk()->assertSee('nombre maximal d’alertes actives (2)');
    $this->post('/mon-espace/alertes', ['commune_id' => $this->cocody->id, 'frequence' => 'instantanee'])->assertForbidden();

    // Une alerte en pause libère une place.
    $this->post('/mon-espace/alertes/'.Alerte::query()->firstOrFail()->id.'/basculer');
    $this->post('/mon-espace/alertes', ['commune_id' => $this->cocody->id, 'frequence' => 'instantanee'])->assertRedirect();

    // Reprendre l'alerte en pause dépasserait la limite.
    $enPause = Alerte::query()->where('active', false)->sole();
    $this->post('/mon-espace/alertes/'.$enPause->id.'/basculer')->assertSessionHasErrors('alerte');
    expect($enPause->refresh()->active)->toBeFalse();
});

it('ignore la limite tant que les quotas sont désactivés', function () {
    Alerte::factory()->count(12)->create(['utilisateur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->post('/mon-espace/alertes', ['commune_id' => $this->cocody->id, 'frequence' => 'quotidienne'])->assertRedirect();

    expect(Alerte::query()->count())->toBe(13);
});

it('modifie, met en pause, reprend et supprime son alerte', function () {
    $alerte = Alerte::factory()->create(['utilisateur_id' => $this->membre->id, 'commune_id' => $this->cocody->id]);
    $this->actingAs($this->membre);

    $this->put('/mon-espace/alertes/'.$alerte->id, ['nom' => 'Mon alerte Cocody', 'commune_id' => $this->cocody->id, 'type' => 'emploi', 'type_contrat' => 'stage', 'frequence' => 'quotidienne']);
    $alerte->refresh();
    expect($alerte->nom)->toBe('Mon alerte Cocody')->and($alerte->type)->toBe(TypePublication::Emploi)->and($alerte->frequence)->toBe(FrequenceAlerte::Quotidienne);

    $this->post('/mon-espace/alertes/'.$alerte->id.'/basculer');
    expect($alerte->refresh()->active)->toBeFalse();
    $this->post('/mon-espace/alertes/'.$alerte->id.'/basculer');
    expect($alerte->refresh()->active)->toBeTrue();

    $this->delete('/mon-espace/alertes/'.$alerte->id);
    expect(Alerte::query()->count())->toBe(0);
});

it('interdit de toucher aux alertes d’un autre membre', function (string $methode, string $suffixe) {
    $alerte = Alerte::factory()->create(['commune_id' => $this->cocody->id]);

    $this->actingAs($this->membre)->{$methode}('/mon-espace/alertes/'.$alerte->id.$suffixe, ['commune_id' => $this->cocody->id, 'frequence' => 'instantanee'])->assertForbidden();

    expect(Alerte::query()->sole()->active)->toBeTrue();
})->with([
    'formulaire' => ['get', '/modifier'],
    'modifier' => ['put', ''],
    'pause' => ['post', '/basculer'],
    'supprimer' => ['delete', ''],
]);

it('ne montre que ses propres alertes et exige d’être connecté', function () {
    Alerte::factory()->create(['nom' => 'Alerte d’un autre']);

    $this->get('/mon-espace/alertes')->assertRedirect(route('login'));
    $this->actingAs($this->membre)->get('/mon-espace/alertes')->assertOk()->assertDontSee('Alerte d’un autre');
});
