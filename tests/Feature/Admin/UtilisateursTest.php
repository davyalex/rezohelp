<?php

use App\Actions\Publication\MettreAJourConfiance;
use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifRejet;
use App\Enums\StatutPublication;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Entreprise;
use App\Models\JournalActivite;
use App\Models\Plan;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    referentielsPublication();
    $this->admin = Utilisateur::factory()->create();
    $this->admin->assignRole('administrateur');
    $this->moderateur = Utilisateur::factory()->create();
    $this->moderateur->assignRole('moderateur');
    $this->membre = Utilisateur::factory()->create(['telephone' => '+2250708091099', 'nom' => 'Kouassi', 'prenoms' => 'Awa']);
});

it('trouve un compte par son numéro sous toutes ses formes ou par son nom', function (string $terme) {
    $reponse = $this->actingAs($this->moderateur)
        ->getJson(route('admin.utilisateurs.index', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => $terme]]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$this->membre->id]);
})->with(['0708091099', '07 08 09 10 99', '+225 07 08 09 10 99', 'Kouassi', 'Awa Kouassi']);

it('suspend un compte avec un motif et retire ses publications du fil', function () {
    $enLigne = Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id]);

    $this->actingAs($this->moderateur)
        ->post(route('admin.utilisateurs.suspendre', $this->membre), ['motif_suspension' => 'Annonces frauduleuses', 'suspendu_jusqu_au' => now()->addDays(7)->toDateString()])
        ->assertSessionHas('statut');

    $this->membre->refresh();
    expect($this->membre->estSuspendu())->toBeTrue()
        ->and($this->membre->suspendu_jusqu_au?->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($enLigne->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and(JournalActivite::query()->where('action', 'utilisateur.suspendu')->sole()->donnees)->toMatchArray(['motif' => 'Annonces frauduleuses', 'publications_retirees' => 1]);

    $this->post(route('admin.utilisateurs.reactiver', $this->membre));
    expect($this->membre->refresh()->estSuspendu())->toBeFalse()
        ->and(JournalActivite::query()->where('action', 'utilisateur.reactive')->exists())->toBeTrue();
});

it('interdit à un administrateur de se suspendre lui-même', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.utilisateurs.suspendre', $this->admin), ['motif_suspension' => 'Test'])
        ->assertSessionHas('erreur', 'Vous ne pouvez pas suspendre votre propre compte.');

    expect($this->admin->refresh()->suspendu_le)->toBeNull();
});

it('interdit de retirer son propre rôle d’administrateur, pas celui d’un autre', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.utilisateurs.roles', $this->admin), ['roles' => ['moderateur']])
        ->assertSessionHas('erreur');
    expect($this->admin->refresh()->hasRole('administrateur'))->toBeTrue();

    $autreAdmin = Utilisateur::factory()->create();
    $autreAdmin->assignRole('administrateur');
    $this->put(route('admin.utilisateurs.roles', $autreAdmin), ['roles' => ['moderateur', 'artisan']])->assertSessionHas('statut');

    expect($autreAdmin->refresh()->getRoleNames()->sort()->values()->all())->toBe(['artisan', 'moderateur'])
        ->and(JournalActivite::query()->where('action', 'utilisateur.roles')->sole()->donnees['apres'])->toBe(['artisan', 'moderateur']);
});

it('réserve la gestion des comptes à l’administrateur', function () {
    $this->actingAs($this->moderateur);

    $this->put(route('admin.utilisateurs.roles', $this->membre), ['roles' => ['administrateur']])->assertForbidden();
    $this->delete(route('admin.utilisateurs.destroy', $this->membre))->assertForbidden();
    $this->post(route('admin.utilisateurs.reinitialiser-mot-de-passe', $this->membre))->assertForbidden();
    expect($this->membre->refresh()->hasRole('administrateur'))->toBeFalse();
});

it('génère un mot de passe temporaire affiché une seule fois, à changer, jamais journalisé', function () {
    $reponse = $this->actingAs($this->admin)->post(route('admin.utilisateurs.reinitialiser-mot-de-passe', $this->membre));

    $temporaire = (string) session('mot_de_passe_temporaire');
    $this->membre->refresh();

    expect(strlen($temporaire))->toBe(12)
        ->and(Hash::check($temporaire, $this->membre->mot_de_passe))->toBeTrue()
        ->and($this->membre->doit_changer_mot_de_passe)->toBeTrue()
        ->and(json_encode(JournalActivite::query()->where('action', 'utilisateur.mot_de_passe_reinitialise')->sole()->donnees))->not->toContain($temporaire);
    $reponse->assertRedirect();
});

it('anonymise un compte (ARTCI) en conservant ses publications', function () {
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id]);
    Alerte::factory()->create(['utilisateur_id' => $this->membre->id]);

    $this->actingAs($this->admin)->delete(route('admin.utilisateurs.destroy', $this->membre))->assertRedirect(route('admin.utilisateurs.index'));

    $anonyme = Utilisateur::withTrashed()->findOrFail($this->membre->id);
    expect($anonyme->trashed())->toBeTrue()
        ->and($anonyme->telephone)->toBeNull()
        ->and($anonyme->nom)->toBe('Utilisateur supprimé')
        ->and(Alerte::query()->where('utilisateur_id', $this->membre->id)->exists())->toBeFalse()
        ->and($publication->refresh()->auteur_nom_affiche)->toBe('Utilisateur supprimé')
        ->and(JournalActivite::query()->where('action', 'utilisateur.anonymise')->exists())->toBeTrue();

    // Jamais son propre compte.
    $this->delete(route('admin.utilisateurs.destroy', $this->admin))->assertSessionHas('erreur');
});

it('lève une restriction durablement : les rejets antérieurs ne comptent plus', function () {
    Publication::factory()->count(2)->create(['auteur_id' => $this->membre->id, 'statut' => StatutPublication::Rejetee, 'motif_rejet' => MotifRejet::ArnaqueSuspectee]);
    $this->membre->forceFill(['confiance' => ConfianceUtilisateur::Restreint])->save();

    $this->actingAs($this->admin)->post(route('admin.utilisateurs.lever-restriction', $this->membre))->assertSessionHas('statut');
    expect($this->membre->refresh()->confiance)->toBe(ConfianceUtilisateur::Nouveau);

    // Un nouvel événement relance le calcul : la restriction ne revient pas.
    app(MettreAJourConfiance::class)->executer($this->membre);
    expect($this->membre->refresh()->confiance)->toBe(ConfianceUtilisateur::Nouveau);
});

it('modifie les informations d’un compte avec l’avant / après dans le journal', function () {
    $this->actingAs($this->admin)->put(route('admin.utilisateurs.update', $this->membre), ['nom' => 'Kouamé', 'prenoms' => 'Awa', 'email' => 'Awa@Exemple.ci'])
        ->assertRedirect(route('admin.utilisateurs.show', $this->membre));

    expect($this->membre->refresh()->nom)->toBe('Kouamé')->and($this->membre->email)->toBe('awa@exemple.ci')
        ->and(JournalActivite::query()->where('action', 'utilisateur.modifie')->sole()->donnees)->toMatchArray(['avant' => ['nom' => 'Kouassi', 'email' => null], 'apres' => ['nom' => 'Kouamé', 'email' => 'awa@exemple.ci']]);
});

it('vérifie une entreprise et met à jour le badge de ses cartes', function () {
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);

    $this->actingAs($this->admin)->post(route('admin.entreprises.verifier', $entreprise));

    expect($entreprise->refresh()->verifiee)->toBeTrue()
        ->and($publication->refresh()->auteur_verifie)->toBeTrue()
        ->and(JournalActivite::query()->where('action', 'entreprise.verifiee')->exists())->toBeTrue();

    $this->actingAs($this->moderateur)->post(route('admin.entreprises.verifier', $entreprise))->assertForbidden();
});

it('fusionne deux fiches d’entreprise : publications et abonnements rattachés, doublon supprimé', function () {
    $conservee = Entreprise::factory()->create(['nom' => 'Batiplus', 'proprietaire_id' => $this->membre->id]);
    $doublon = Entreprise::factory()->create(['nom' => 'Bati Plus', 'proprietaire_id' => $this->membre->id]);
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $doublon->id, 'auteur_nom_affiche' => 'Bati Plus']);
    $abonnement = Abonnement::factory()->create(['titulaire_type' => 'entreprise', 'titulaire_id' => $doublon->id, 'plan_id' => Plan::query()->where('code', 'entreprise_30j')->firstOrFail()->id]);

    $this->actingAs($this->admin)->post(route('admin.entreprises.fusionner', $doublon), ['entreprise_conservee_id' => $conservee->id])
        ->assertRedirect(route('admin.entreprises.show', $conservee));

    expect($publication->refresh()->entreprise_id)->toBe($conservee->id)
        ->and($publication->auteur_nom_affiche)->toBe('Batiplus')
        ->and($abonnement->refresh()->titulaire_id)->toBe($conservee->id)
        ->and($doublon->refresh()->trashed())->toBeTrue()
        ->and(JournalActivite::query()->where('action', 'entreprise.fusionnee')->sole()->donnees)->toMatchArray(['doublon_id' => $doublon->id, 'publications' => 1]);

    $this->post(route('admin.entreprises.fusionner', $conservee), ['entreprise_conservee_id' => $conservee->id])->assertSessionHas('erreur');
});
