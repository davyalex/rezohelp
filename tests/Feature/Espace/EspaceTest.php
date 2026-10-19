<?php

use App\Actions\Compte\AnonymiserUtilisateur;
use App\Enums\TypePublication;
use App\Models\Alerte;
use App\Models\Entreprise;
use App\Models\EssaiUtilise;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationRemiseEnAttente;
use App\Services\TelephoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    referentielsPublication();
    $this->membre = Utilisateur::factory()->create(['telephone' => '+2250708091011', 'prenoms' => 'Awa', 'nom' => 'Koné']);
});

function notifier(Utilisateur $membre, string $titre, string $lien): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id,
        'type' => PublicationRemiseEnAttente::class,
        'notifiable_type' => 'utilisateur',
        'notifiable_id' => $membre->id,
        'data' => json_encode(['titre' => $titre, 'message' => 'Détail', 'lien' => $lien]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('affiche l’accueil de Mon espace et exige d’être connecté', function () {
    $this->get('/mon-espace')->assertRedirect(route('login'));

    $this->actingAs($this->membre)->get('/mon-espace')
        ->assertOk()
        ->assertSee('Bonjour Awa')
        ->assertSee('Mes publications')->assertSee('Mes alertes')->assertSee('Notifications')->assertSee('Enregistrés');
});

it('montre la pastille de la cloche, liste les notifications et les marque lues', function () {
    $id = notifier($this->membre, 'Nouvelle publication pour votre alerte', url('/publication/exemple'));
    notifier($this->membre, 'Deuxième notification', url('/'));

    $this->actingAs($this->membre)->get('/')->assertSee('Notifications : 2 non lue(s)');
    $this->get('/mon-espace/notifications')->assertOk()->assertSee('Nouvelle publication pour votre alerte')->assertSee('non lue');

    $this->get('/mon-espace/notifications/'.$id)->assertRedirect(url('/publication/exemple'));
    expect($this->membre->unreadNotifications()->count())->toBe(1);

    $this->post('/mon-espace/notifications/tout-lu')->assertRedirect();
    expect($this->membre->unreadNotifications()->count())->toBe(0);
    $this->get('/')->assertDontSee('non lue(s)');
});

it('ne suit jamais un lien externe stocké dans une notification', function () {
    $id = notifier($this->membre, 'Lien piégé', 'https://site-malveillant.example/hameçon');

    $this->actingAs($this->membre)->get('/mon-espace/notifications/'.$id)->assertRedirect(route('espace.notifications'));
});

it('interdit d’ouvrir la notification d’un autre membre', function () {
    $id = notifier(Utilisateur::factory()->create(), 'Privé', url('/'));

    $this->actingAs($this->membre)->get('/mon-espace/notifications/'.$id)->assertNotFound();
});

it('liste les publications enregistrées', function () {
    $enregistree = Publication::factory()->publiee()->create(['titre' => 'Offre gardée de côté']);
    $retiree = Publication::factory()->create(['titre' => 'Offre retirée du fil']);
    $this->membre->publicationsEnregistrees()->attach([$enregistree->id => ['cree_le' => now()], $retiree->id => ['cree_le' => now()]]);
    Publication::factory()->publiee()->create(['titre' => 'Offre non enregistrée']);

    $this->actingAs($this->membre)->get('/mon-espace/enregistres')
        ->assertOk()
        ->assertSee('Offre gardée de côté')
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('Cette publication n’est plus en ligne')
        ->assertDontSee('Offre non enregistrée');
});

it('modifie le profil et rafraîchit le nom affiché sur ses publications', function () {
    $publication = Publication::factory()->create(['auteur_id' => $this->membre->id, 'auteur_nom_affiche' => 'Awa K.']);
    $publicationEntreprise = Publication::factory()->create(['auteur_id' => $this->membre->id, 'entreprise_id' => Entreprise::factory()->create()->id, 'auteur_nom_affiche' => 'SARL Exemple']);

    $this->actingAs($this->membre)->put('/mon-espace/profil', ['prenoms' => 'Aminata', 'nom' => 'Koné', 'email' => 'AMINATA@exemple.ci'])->assertRedirect();

    expect($this->membre->refresh()->prenoms)->toBe('Aminata')
        ->and($this->membre->email)->toBe('aminata@exemple.ci')
        ->and($publication->refresh()->auteur_nom_affiche)->toBe('Aminata K.')
        ->and($publicationEntreprise->refresh()->auteur_nom_affiche)->toBe('SARL Exemple');
});

it('change de numéro après vérification d’un code envoyé au nouveau numéro', function () {
    $sms = fauxSms();
    $this->actingAs($this->membre);

    $this->post('/mon-espace/profil/numero', ['pays_code' => 'CI', 'telephone' => '05 06 07 08 09'])->assertRedirect(route('espace.numero'));
    expect($sms->dernierCode('+2250506070809'))->not->toBeNull()
        ->and($this->membre->refresh()->telephone)->toBe('+2250708091011');

    $this->post('/mon-espace/profil/numero/confirmer', ['code' => $sms->dernierCode('+2250506070809')])->assertRedirect(route('espace.profil'));

    $this->membre->refresh();
    expect($this->membre->telephone)->toBe('+2250506070809')
        ->and($this->membre->telephone_verifie_le)->not->toBeNull();
});

it('refuse un nouveau numéro déjà utilisé ou un code faux', function () {
    $sms = fauxSms();
    Utilisateur::factory()->create(['telephone' => '+2250102030405']);
    $this->actingAs($this->membre);

    $this->post('/mon-espace/profil/numero', ['pays_code' => 'CI', 'telephone' => '01 02 03 04 05'])->assertSessionHasErrors('telephone');

    $this->post('/mon-espace/profil/numero', ['pays_code' => 'CI', 'telephone' => '05 06 07 08 09']);
    $faux = $sms->dernierCode('+2250506070809') === '000000' ? '111111' : '000000';
    $this->post('/mon-espace/profil/numero/confirmer', ['code' => $faux])->assertSessionHasErrors('code');

    expect($this->membre->refresh()->telephone)->toBe('+2250708091011');
});

it('supprime le compte en anonymisant les données (ARTCI)', function () {
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id, 'auteur_nom_affiche' => 'Awa K.', 'type' => TypePublication::Service]);
    Alerte::factory()->count(2)->create(['utilisateur_id' => $this->membre->id]);
    notifier($this->membre, 'Une notification', url('/'));
    EssaiUtilise::query()->create(['empreinte_telephone' => app(TelephoneService::class)->empreinte('+2250708091011'), 'famille' => 'alertes_sms', 'utilise_le' => now()]);

    $this->actingAs($this->membre)->delete('/mon-espace/profil', ['mot_de_passe_actuel' => 'faux'])->assertSessionHasErrors('mot_de_passe_actuel');
    $this->delete('/mon-espace/profil', ['mot_de_passe_actuel' => 'mot-de-passe-test'])->assertRedirect(route('fil.index'));

    $this->assertGuest();
    $supprime = Utilisateur::withTrashed()->findOrFail($this->membre->id);
    expect($supprime->trashed())->toBeTrue()
        ->and($supprime->nom)->toBe(AnonymiserUtilisateur::NOM_ANONYME)
        ->and($supprime->prenoms)->toBeNull()
        ->and($supprime->telephone)->toBeNull()
        ->and(Alerte::query()->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and($publication->refresh()->auteur_nom_affiche)->toBe(AnonymiserUtilisateur::NOM_ANONYME)
        // L'empreinte des essais survit, pour empêcher un nouvel essai avec le même numéro (§14.6).
        ->and(EssaiUtilise::query()->count())->toBe(1);

    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])->assertSessionHasErrors('telephone');
    // Le numéro libéré peut servir à un nouveau compte.
    expect(Utilisateur::query()->where('telephone', '+2250708091011')->exists())->toBeFalse();
});

it('insère le bloc « Créer une alerte » après la 5e carte, avec les filtres du fil', function () {
    Publication::factory()->count(8)->publiee(now()->subMinutes(5))->create(['type' => TypePublication::Emploi]);

    $html = (string) $this->get('/emplois?aujourdhui=1')->getContent();
    $positionBloc = strpos($html, 'Ne ratez plus une offre');
    preg_match_all('/<article /', substr($html, 0, (int) $positionBloc), $avant);

    expect($positionBloc)->not->toBeFalse()
        ->and(count($avant[0]))->toBe(5)
        ->and($html)->toContain(e(route('alertes.depuis-fil', ['aujourdhui' => '1', 'type' => 'emploi'])));
});
