<?php

use App\Actions\Admin\GererAdministrateur;
use App\Exceptions\OperationImpossibleException;
use App\Models\Administrateur;
use App\Models\JournalActivite;
use App\Models\Utilisateur;
use App\Support\PermissionsAdmin;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    referentielsPublication();
    $this->super = Administrateur::factory()->superAdministrateur()->create(['nom_utilisateur' => 'chef']);
    $this->admin = Administrateur::factory()->administrateur()->create(['nom_utilisateur' => 'awa.k']);
    $this->moderateur = Administrateur::factory()->moderateur()->create();
});

// --- Connexion ------------------------------------------------------------------------------------

it('connecte l’équipe par nom d’utilisateur et mot de passe, sur la garde admin seulement', function () {
    $this->get(route('admin.connexion'))->assertOk()->assertSee('Nom d’utilisateur');

    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => ' AWA.K ', 'mot_de_passe' => 'Admin123'])
        ->assertRedirect(route('admin.tableau-de-bord'));

    $this->assertAuthenticatedAs($this->admin, 'admin');
    $this->assertGuest('web');
    expect($this->admin->refresh()->derniere_connexion_le)->not->toBeNull();
});

it('refuse un mauvais mot de passe, un compte suspendu, et un identifiant de membre', function () {
    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'awa.k', 'mot_de_passe' => 'mauvais'])
        ->assertSessionHasErrors('nom_utilisateur');

    Administrateur::factory()->administrateur()->suspendu()->create(['nom_utilisateur' => 'suspendu']);
    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'suspendu', 'mot_de_passe' => 'Admin123'])
        ->assertSessionHasErrors(['nom_utilisateur' => 'Ce compte est suspendu. Contactez un super administrateur.']);

    $membre = Utilisateur::factory()->create();
    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => $membre->telephone, 'mot_de_passe' => 'mot-de-passe-test'])
        ->assertSessionHasErrors('nom_utilisateur');

    $this->assertGuest('admin');
});

it('limite les tentatives de connexion à 5 par minute', function () {
    foreach (range(1, 5) as $essai) {
        $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'awa.k', 'mot_de_passe' => 'mauvais']);
    }

    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'awa.k', 'mot_de_passe' => 'Admin123'])->assertStatus(429);
});

it('limite aussi les essais en série sur plusieurs noms d’utilisateur depuis une même connexion', function () {
    foreach (range(1, 20) as $essai) {
        $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'inconnu'.$essai, 'mot_de_passe' => 'mauvais']);
    }

    $this->post(route('admin.connexion.store'), ['nom_utilisateur' => 'awa.k', 'mot_de_passe' => 'Admin123'])->assertStatus(429);
});

it('refuse un nouveau mot de passe d’administrateur apparu dans une fuite de données', function () {
    $admin = Administrateur::factory()->moderateur()->motDePasseAChanger()->create();
    $this->declarerCompromis('Password123');

    $this->actingAs($admin, 'admin')->put(route('admin.mot-de-passe.update'), [
        'mot_de_passe_actuel' => 'Admin123',
        'mot_de_passe' => 'Password123',
        'mot_de_passe_confirmation' => 'Password123',
    ])->assertSessionHasErrors('mot_de_passe');
});

it('impose de changer un mot de passe provisoire avant toute autre page', function () {
    $nouveau = Administrateur::factory()->moderateur()->motDePasseAChanger()->create();

    $this->actingAs($nouveau, 'admin')->get(route('admin.moderation.index'))->assertRedirect(route('admin.mot-de-passe.edit'));
    $this->get(route('admin.mot-de-passe.edit'))->assertOk()->assertSee('Choisissez votre mot de passe');

    $this->put(route('admin.mot-de-passe.update'), [
        'mot_de_passe_actuel' => 'Admin123',
        'mot_de_passe' => 'Nouveau2026',
        'mot_de_passe_confirmation' => 'Nouveau2026',
    ])->assertRedirect(route('admin.tableau-de-bord'));

    expect($nouveau->refresh())
        ->doit_changer_mot_de_passe->toBeFalse()
        ->and(Hash::check('Nouveau2026', $nouveau->mot_de_passe))->toBeTrue();
    $this->get(route('admin.moderation.index'))->assertOk();
});

it('déconnecte au clic suivant un compte suspendu entre-temps', function () {
    $this->actingAs($this->moderateur, 'admin')->get(route('admin.tableau-de-bord'))->assertOk();
    $this->moderateur->forceFill(['suspendu_le' => now()])->save();

    $this->get(route('admin.tableau-de-bord'))->assertRedirect(route('admin.connexion'));
    $this->assertGuest('admin');
});

it('se déconnecte du back-office sans toucher à la session membre', function () {
    $membre = Utilisateur::factory()->create();
    $this->actingAs($membre)->actingAs($this->admin, 'admin')
        ->post(route('admin.deconnexion'))->assertRedirect(route('admin.connexion'));

    $this->assertGuest('admin');
    $this->assertAuthenticatedAs($membre, 'web');
});

// --- Comptes de l'équipe ----------------------------------------------------------------------------

it('génère un mot de passe de 8 caractères (minuscule, majuscule, chiffre)', function () {
    foreach (range(1, 50) as $essai) {
        expect(GererAdministrateur::genererMotDePasse())->toMatch('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[2-9])[A-Za-z2-9]{8}$/');
    }
});

it('crée un administrateur avec un mot de passe généré affiché une seule fois', function () {
    $reponse = $this->actingAs($this->super, 'admin')->post(route('admin.administrateurs.store'), [
        'prenoms' => 'Koffi', 'nom' => 'Yao', 'nom_utilisateur' => 'Koffi.Yao', 'email' => '', 'role' => PermissionsAdmin::ROLE_MODERATEUR,
    ])->assertRedirect(route('admin.administrateurs.index'));

    $genere = $reponse->getSession()->get('mot_de_passe_genere');
    $compte = Administrateur::query()->where('nom_utilisateur', 'koffi.yao')->sole();

    expect($genere['mot_de_passe'])->toHaveLength(8)
        ->and(Hash::check($genere['mot_de_passe'], $compte->mot_de_passe))->toBeTrue()
        ->and($compte->doit_changer_mot_de_passe)->toBeTrue()
        ->and($compte->hasRole(PermissionsAdmin::ROLE_MODERATEUR))->toBeTrue()
        ->and(JournalActivite::query()->where('action', 'administrateur.cree')->sole()->donnees)->toBe(['role' => 'moderateur'])
        ->and(json_encode(JournalActivite::query()->get()->toArray()))->not->toContain($genere['mot_de_passe']);
});

it('réserve la gestion de l’équipe au super administrateur par défaut', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.administrateurs.index'))->assertForbidden();
    $this->actingAs($this->admin, 'admin')->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($this->super, 'admin')->get(route('admin.administrateurs.index'))->assertOk();
    $this->actingAs($this->super, 'admin')->get(route('admin.roles.index'))->assertOk()->assertSee('Super administrateur');
});

it('protège un super administrateur contre un administrateur, même avec la permission', function () {
    Role::findByName(PermissionsAdmin::ROLE_ADMIN, 'admin')->givePermissionTo('administrateurs.gerer');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.administrateurs.suspendre', $this->super))
        ->assertSessionHas('erreur', 'Seul un super administrateur peut gérer un super administrateur.');
    $this->post(route('admin.administrateurs.store'), [
        'nom' => 'Pirate', 'nom_utilisateur' => 'pirate', 'role' => PermissionsAdmin::ROLE_SUPER,
    ])->assertSessionHas('erreur', 'Seul un super administrateur peut attribuer ce rôle.');

    expect($this->super->refresh()->suspendu_le)->toBeNull()
        ->and(Administrateur::query()->where('nom_utilisateur', 'pirate')->exists())->toBeFalse();
});

it('interdit de se suspendre, de se supprimer ou de changer son propre rôle', function () {
    $this->actingAs($this->super, 'admin');

    $this->post(route('admin.administrateurs.suspendre', $this->super))->assertSessionHas('erreur');
    $this->delete(route('admin.administrateurs.destroy', $this->super))->assertSessionHas('erreur');
    $this->put(route('admin.administrateurs.update', $this->super), [
        'nom' => $this->super->nom, 'nom_utilisateur' => 'chef', 'role' => PermissionsAdmin::ROLE_ADMIN,
    ])->assertSessionHas('erreur', 'Vous ne pouvez pas changer votre propre rôle.');

    expect($this->super->refresh())->estSuperAdministrateur()->toBeTrue()->suspendu_le->toBeNull();
});

it('garde toujours au moins un super administrateur actif', function () {
    $second = Administrateur::factory()->superAdministrateur()->create();
    $this->actingAs($this->super, 'admin');

    $this->post(route('admin.administrateurs.suspendre', $second))->assertSessionHas('statut');
    // $second suspendu : $this->super est le dernier actif ; un autre super ne peut plus le suspendre.
    expect(fn () => app(GererAdministrateur::class)->suspendre($this->super, $second))
        ->toThrow(OperationImpossibleException::class, 'Il doit rester au moins un super administrateur actif.');
});

it('réinitialise le mot de passe, suspend, réactive et supprime un compte', function () {
    $this->actingAs($this->super, 'admin');

    $reponse = $this->post(route('admin.administrateurs.reinitialiser-mot-de-passe', $this->moderateur))->assertSessionHas('statut');
    $motDePasse = $reponse->getSession()->get('mot_de_passe_genere')['mot_de_passe'];
    expect(Hash::check($motDePasse, $this->moderateur->refresh()->mot_de_passe))->toBeTrue()
        ->and($this->moderateur->doit_changer_mot_de_passe)->toBeTrue();

    $this->post(route('admin.administrateurs.suspendre', $this->moderateur))->assertSessionHas('statut');
    expect($this->moderateur->refresh()->estSuspendu())->toBeTrue();
    $this->post(route('admin.administrateurs.reactiver', $this->moderateur))->assertSessionHas('statut');
    expect($this->moderateur->refresh()->estSuspendu())->toBeFalse();

    $this->delete(route('admin.administrateurs.destroy', $this->moderateur))->assertRedirect(route('admin.administrateurs.index'));
    expect(Administrateur::query()->find($this->moderateur->id))->toBeNull()
        ->and(Administrateur::query()->where('nom_utilisateur', $this->moderateur->nom_utilisateur)->exists())->toBeFalse();
});

it('sert la liste des administrateurs en DataTables avec un menu d’actions', function () {
    $reponse = $this->actingAs($this->super, 'admin')
        ->getJson(route('admin.administrateurs.index', ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    $lignes = collect($reponse->json('data'));
    expect($lignes)->toHaveCount(3)
        ->and($lignes->pluck('actions')->implode(''))->toContain('popovertarget="actions-administrateur-'.$this->moderateur->id.'"');
});

// --- Rôles et permissions ---------------------------------------------------------------------------

it('crée un rôle avec ses permissions, qui ouvrent les modules correspondants', function () {
    $this->actingAs($this->super, 'admin')->post(route('admin.roles.store'), [
        'libelle' => 'Support client',
        'permissions' => ['tableau_de_bord.voir', 'utilisateurs.voir', 'cle.inconnue'],
    ])->assertSessionHasErrors('permissions.2');

    $this->post(route('admin.roles.store'), [
        'libelle' => 'Support client',
        'permissions' => ['tableau_de_bord.voir', 'utilisateurs.voir'],
    ])->assertRedirect();

    $role = Role::findByName('support_client', 'admin');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['tableau_de_bord.voir', 'utilisateurs.voir']);

    $support = Administrateur::factory()->create();
    $support->assignRole('support_client');
    $this->actingAs($support, 'admin')->get(route('admin.utilisateurs.index'))->assertOk();
    $this->actingAs($support, 'admin')->get(route('admin.moderation.index'))->assertForbidden();
});

it('modifie les permissions d’un rôle livré mais ne touche ni au super administrateur ni aux rôles livrés', function () {
    $this->actingAs($this->super, 'admin');
    $moderateur = Role::findByName(PermissionsAdmin::ROLE_MODERATEUR, 'admin');

    $this->put(route('admin.roles.update', $moderateur), ['permissions' => ['tableau_de_bord.voir']])->assertSessionHas('statut');
    expect($moderateur->refresh()->permissions->pluck('name')->all())->toBe(['tableau_de_bord.voir']);

    $super = Role::findByName(PermissionsAdmin::ROLE_SUPER, 'admin');
    $this->put(route('admin.roles.update', $super), ['permissions' => []])->assertSessionHas('erreur');
    $this->delete(route('admin.roles.destroy', $moderateur))->assertSessionHas('erreur');
    expect(Role::query()->where('name', PermissionsAdmin::ROLE_MODERATEUR)->exists())->toBeTrue();
});

it('refuse de supprimer un rôle encore attribué', function () {
    $this->actingAs($this->super, 'admin')->post(route('admin.roles.store'), ['libelle' => 'Comptabilité', 'permissions' => ['paiements.voir']]);
    $comptable = Administrateur::factory()->create();
    $comptable->assignRole('comptabilite');
    $role = Role::findByName('comptabilite', 'admin');

    $this->delete(route('admin.roles.destroy', $role))->assertSessionHas('erreur');
    $comptable->syncRoles([PermissionsAdmin::ROLE_MODERATEUR]);
    $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));
    expect(Role::query()->where('name', 'comptabilite')->exists())->toBeFalse();
});

it('donne au super administrateur une permission ajoutée après coup', function () {
    expect($this->super->can('permission.future'))->toBeTrue()
        ->and($this->admin->can('permission.future'))->toBeFalse();
});
