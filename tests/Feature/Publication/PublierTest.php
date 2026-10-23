<?php

use App\Enums\ConfianceUtilisateur;
use App\Enums\StatutPublication;
use App\Events\PublicationPubliee;
use App\Jobs\EnrichirPublicationIA;
use App\Models\Commune;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    referentielsPublication();
    Queue::fake([EnrichirPublicationIA::class]);
    $this->membre = Utilisateur::factory()->create(['telephone' => '+2250708091011']);
    $this->membre->assignRole('membre');
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
});

it('demande de se connecter pour publier et ramène au formulaire choisi', function () {
    auth()->logout();

    $this->get('/publier/service-offre')->assertRedirect(route('login'));
    $this->post('/connexion', ['pays_code' => 'CI', 'telephone' => '0708091011', 'password' => 'mot-de-passe-test'])
        ->assertRedirect(url('/publier/service-offre'));
});

it('affiche les quatre parcours et chaque formulaire', function () {
    $this->actingAs($this->membre)->get('/publier')
        ->assertOk()
        ->assertSee('Je recrute')->assertSee('Je propose mes services')->assertSee('Je cherche un prestataire')->assertSee('Je propose une formation');

    foreach (['emploi', 'service-offre', 'service-demande', 'formation'] as $type) {
        $this->get('/publier/'.$type)->assertOk()->assertSee('Enregistrer en brouillon');
    }

    $this->get('/publier/inconnu')->assertNotFound();
});

it('met en attente la première publication, quel que soit le type', function (string $type, Closure $donnees) {
    $this->actingAs($this->membre)->post('/publier/'.$type, $donnees())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $publication = Publication::query()->sole();
    expect($publication->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->auteur_id)->toBe($this->membre->id)
        ->and($publication->slug)->not->toBe('')
        ->and($publication->code_court)->toHaveLength(6)
        ->and($publication->extrait)->not->toBe('')
        ->and($publication->auteur_nom_affiche)->toBe($this->membre->nomAffiche())
        ->and($publication->expire_le?->isAfter(now()->addDays(29)))->toBeTrue();

    $this->get(route('publier.confirmation', $publication->id))->assertOk()->assertSee('visible après vérification');
    Queue::assertPushed(EnrichirPublicationIA::class);
})->with([
    'emploi' => ['emploi', fn () => donneesEmploi()],
    'service offre' => ['service-offre', fn () => donneesServiceOffre()],
    'service demande' => ['service-demande', fn () => donneesServiceDemande()],
    'formation' => ['formation', fn () => donneesFormation()],
]);

it('enregistre les détails, les faits clés et le rôle déduit du type', function () {
    $this->actingAs($this->membre)->post('/publier/service-offre', donneesServiceOffre());

    $publication = Publication::query()->with('detailService')->sole();
    expect($publication->detailService->telephone_contact)->toBe('+2250506070809')
        ->and($publication->detailService->whatsapp)->toBe('+2250506070809')
        ->and($publication->detailService->metier)->toBe('Électricité')
        ->and($publication->detailService->zones_intervention)->toHaveCount(2)
        ->and($publication->faits_cles)->toContain('Électricité')
        ->and($this->membre->refresh()->hasRole('artisan'))->toBeTrue();

    $this->post('/publier/emploi', donneesEmploi());
    expect($this->membre->refresh()->hasRole('recruteur'))->toBeTrue();
});

it('crée l’entreprise directement dans le formulaire, avec son logo', function () {
    Storage::fake('public');

    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi([
        'employeur' => 'nouvelle',
        'entreprise_nom' => 'Boulangerie du Plateau',
        'entreprise_secteur' => 'Commerce',
        'entreprise_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ]))->assertSessionHasNoErrors();

    $entreprise = Entreprise::query()->sole();
    $publication = Publication::query()->sole();
    expect($entreprise->proprietaire_id)->toBe($this->membre->id)
        ->and($entreprise->getFirstMedia('logo'))->not->toBeNull()
        ->and($publication->entreprise_id)->toBe($entreprise->id)
        ->and($publication->auteur_nom_affiche)->toBe('Boulangerie du Plateau')
        ->and($publication->auteur_avatar_url)->toContain('avatar');
});

it('refuse l’entreprise d’un autre membre', function () {
    $autre = Entreprise::factory()->create();

    $this->actingAs($this->membre)
        ->post('/publier/emploi', donneesEmploi(['employeur' => 'entreprise', 'entreprise_id' => $autre->id]))
        ->assertSessionHasErrors('entreprise_id');

    expect(Publication::query()->count())->toBe(0);
});

it('publie directement pour un auteur fiable et déclenche PublicationPubliee', function () {
    Event::fake([PublicationPubliee::class]);
    $this->membre->forceFill(['confiance' => ConfianceUtilisateur::Fiable])->save();
    Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->post('/publier/formation', donneesFormation())->assertSessionHasNoErrors();

    $publication = Publication::query()->where('titre', 'Bootcamp développeur web')->sole();
    expect($publication->statut)->toBe(StatutPublication::Publiee)
        ->and($publication->publiee_le)->not->toBeNull()
        ->and($publication->tri_le)->not->toBeNull();
    Event::assertDispatched(PublicationPubliee::class, fn (PublicationPubliee $evenement): bool => $evenement->publication->is($publication));

    $this->get(route('publier.confirmation', $publication->id))->assertSee('est en ligne')->assertSee('Partager sur WhatsApp');
    $this->get('/')->assertSee('Bootcamp développeur web');
});

it('publie directement pour une entreprise vérifiée sans signal', function () {
    Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id]);
    $entreprise = Entreprise::factory()->verifiee()->create(['proprietaire_id' => $this->membre->id]);

    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi(['employeur' => 'entreprise', 'entreprise_id' => $entreprise->id]));

    $publication = Publication::query()->where('entreprise_id', $entreprise->id)->sole();
    expect($publication->statut)->toBe(StatutPublication::Publiee)
        ->and($publication->auteur_verifie)->toBeTrue();
});

it('met en attente une offre contenant « frais de dossier », avec le signal visible', function () {
    Publication::factory()->publiee()->create(['auteur_id' => $this->membre->id]);
    $entreprise = Entreprise::factory()->verifiee()->create(['proprietaire_id' => $this->membre->id]);

    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi([
        'employeur' => 'entreprise',
        'entreprise_id' => $entreprise->id,
        'contenu' => contenuValide('Des frais de dossier de 5 000 FCFA sont à prévoir.'),
    ]));

    $publication = Publication::query()->where('entreprise_id', $entreprise->id)->sole();
    expect($publication->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->signaux_risque)->toContain('expression_a_risque');
});

it('enregistre un brouillon sans consommer de quota ni passer en modération', function () {
    $this->actingAs($this->membre)
        ->post('/publier/emploi', donneesEmploi(['action' => 'brouillon']))
        ->assertRedirect(route('espace.publications.index'));

    expect(Publication::query()->sole()->statut)->toBe(StatutPublication::Brouillon);
    Queue::assertNotPushed(EnrichirPublicationIA::class);
});

it('valide les champs communs et propres au parcours', function () {
    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi([
        'titre' => 'Court',
        'contenu' => 'Trop court.',
        'type_contrat' => 'inconnu',
        'salaire_min' => 400000,
        'salaire_max' => 100000,
    ]))->assertSessionHasErrors(['titre', 'contenu', 'type_contrat', 'salaire_max']);

    $this->post('/publier/formation', donneesFormation(['modalite' => 'en_ligne', 'commune_id' => null]))->assertSessionHasNoErrors();
    $this->post('/publier/formation', donneesFormation(['titre' => 'Formation en salle à Marcory', 'commune_id' => null]))->assertSessionHasErrors('commune_id');
});

it('valide le contact selon le mode et refuse tout lien autre que http(s)', function (string $mode, string $contact, bool $valide) {
    $reponse = $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi(['mode_candidature' => $mode, 'contact_candidature' => $contact]));

    $valide ? $reponse->assertSessionHasNoErrors() : $reponse->assertSessionHasErrors('contact_candidature');
})->with([
    'e-mail valide' => ['email', 'rh@entreprise.ci', true],
    'e-mail invalide' => ['email', 'pas-un-email', false],
    'lien https' => ['lien', 'https://carrieres.entreprise.ci', true],
    'lien javascript' => ['lien', 'javascript:alert(1)', false],
    'téléphone valide' => ['telephone', '07 08 09 10 11', true],
    'téléphone invalide' => ['telephone', '1234', false],
    'contact manquant' => ['email', '', false],
]);

it('normalise le numéro de candidature en E.164', function () {
    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi(['mode_candidature' => 'telephone', 'contact_candidature' => '07 08 09 10 11']));

    expect(Publication::query()->with('detailEmploi')->sole()->detailEmploi->contact_candidature)->toBe('+2250708091011');
});

it('limite le nombre de publications par jour', function () {
    parametre('max_publications_par_jour', '2');
    $this->actingAs($this->membre);

    $this->post('/publier/service-demande', donneesServiceDemande())->assertSessionHasNoErrors();
    $this->post('/publier/service-demande', donneesServiceDemande(['titre' => 'Cherche électricien pour des prises']))->assertSessionHasNoErrors();
    $this->post('/publier/service-demande', donneesServiceDemande(['titre' => 'Cherche maçon pour une clôture']))->assertForbidden();

    $this->get('/publier/service-demande')->assertOk()->assertSee('limite de 2 publications par jour');
    expect(Publication::query()->count())->toBe(2);
});

it('limite les envois du formulaire à max_envois_par_heure', function () {
    parametre('max_envois_par_heure', '2');
    $this->actingAs($this->membre);

    $this->post('/publier/emploi', donneesEmploi(['titre' => 'Court']))->assertStatus(302);
    $this->post('/publier/emploi', donneesEmploi(['titre' => 'Court']))->assertStatus(302);
    $this->post('/publier/emploi', donneesEmploi())->assertStatus(429);
});

it('bloque un doublon du même auteur sur 7 jours et propose de prolonger', function () {
    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi(['titre' => 'Comptable confirmé (CDI) Plateau']));

    $this->post('/publier/emploi', donneesEmploi(['titre' => 'Comptable confirmé (CDI) Plateau !']))
        ->assertSessionHasErrors(['titre' => 'Vous avez déjà publié « Comptable confirmé (CDI) Plateau » il y a moins de 7 jours. Prolongez cette annonce depuis « Mes publications » plutôt que d’en publier une nouvelle.']);

    $this->travel(8)->days();
    $this->post('/publier/emploi', donneesEmploi(['titre' => 'Comptable confirmé (CDI) Plateau !']))->assertSessionHasNoErrors();

    expect(Publication::query()->count())->toBe(2);
});

it('n’affiche jamais le numéro d’un demandeur de service', function () {
    $this->actingAs($this->membre)->post('/publier/service-demande', donneesServiceDemande());
    $publication = Publication::query()->with('detailService')->sole();
    $publication->forceFill(['statut' => StatutPublication::Publiee, 'publiee_le' => now(), 'tri_le' => now()])->save();

    expect($publication->detailService->telephone_contact)->toBeNull();

    auth()->logout();
    foreach (['/', '/services', '/publication/'.$publication->slug] as $page) {
        $this->get($page)->assertOk()->assertDontSee('0708091011')->assertDontSee('07 08 09 10 11')->assertDontSee('2250708091011');
    }
});

it('rend l’aperçu avec le vrai composant de carte, sans rien enregistrer', function () {
    $html = (string) $this->actingAs($this->membre)
        ->post('/publier/service-offre/apercu', donneesServiceOffre())
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<article')
        ->toContain('Électricien disponible à Cocody')
        ->toContain('Voir plus')
        ->not->toContain('⟦');
    expect(Publication::query()->count())->toBe(0)
        ->and(Entreprise::query()->count())->toBe(0);

    $this->postJson('/publier/service-offre/apercu', donneesServiceOffre(['titre' => '']))->assertStatus(422);
});

it('affiche l’écran « Quota atteint » quand les quotas sont actifs et épuisés', function () {
    parametre('quotas_actifs', '1');
    // Particulier : 1 offre d'emploi par mois (plan membre gratuit).
    Publication::factory()->emploi()->enAttente()->create(['auteur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->get('/publier/emploi')->assertOk()->assertSee('Vous avez atteint votre limite');
    $this->post('/publier/emploi', donneesEmploi())->assertOk()->assertSee('Vous avez atteint votre limite');

    expect(Publication::query()->count())->toBe(1);
});

it('ignore les quotas tant que quotas_actifs est désactivé', function () {
    Publication::factory()->count(3)->emploi()->enAttente()->create(['auteur_id' => $this->membre->id]);

    $this->actingAs($this->membre)->post('/publier/emploi', donneesEmploi())->assertSessionHasNoErrors();

    expect(Publication::query()->count())->toBe(4);
});

it('interdit de publier à un compte restreint ou suspendu, avec la raison', function () {
    $this->membre->forceFill(['confiance' => ConfianceUtilisateur::Restreint])->save();

    $this->actingAs($this->membre)->get('/publier')->assertSee('Votre compte ne peut plus publier');
    $this->get('/publier/emploi')->assertSee('Votre compte ne peut plus publier');
    $this->post('/publier/emploi', donneesEmploi())->assertForbidden();
    expect(Publication::query()->count())->toBe(0);
});

it('limite le nombre de photos au plan et les convertit en WebP pour la carte', function () {
    Storage::fake('public');
    $this->actingAs($this->membre);

    $this->post('/publier/service-offre', donneesServiceOffre([
        'photos' => array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg", 1200, 900), range(1, 5)),
    ]))->assertSessionHasErrors('photos');

    $this->post('/publier/service-offre', donneesServiceOffre([
        'photos' => [UploadedFile::fake()->image('chantier.jpg', 1200, 900)],
    ]))->assertSessionHasNoErrors();

    $publication = Publication::query()->sole();
    expect($publication->getMedia('photos'))->toHaveCount(1)
        ->and($publication->image_url)->toContain('.webp')
        ->and($publication->image_largeur)->toBe(640)
        ->and($publication->image_hauteur)->toBe(480);
});

it('refuse un fichier qui n’est pas une image', function () {
    Storage::fake('public');

    $this->actingAs($this->membre)->post('/publier/service-offre', donneesServiceOffre([
        'photos' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')],
    ]))->assertSessionHasErrors('photos.0');
});
