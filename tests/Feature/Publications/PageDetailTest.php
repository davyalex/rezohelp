<?php

use App\Enums\ModeCandidature;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Models\DetailEmploi;
use App\Models\DetailService;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;

beforeEach(fn () => $this->seed([ParametresSeeder::class, RolesEtPermissionsSeeder::class]));

it('affiche une publication publiée et compte la vue', function () {
    $publication = Publication::factory()->publiee()->create(['contenu' => "Première ligne\nDeuxième ligne", 'nb_vues' => 0]);

    $this->get('/publication/'.$publication->slug)
        ->assertOk()
        ->assertSee($publication->titre)
        ->assertSee('Première ligne<br />', false);

    expect($publication->refresh()->nb_vues)->toBe(1);
});

it('renvoie 404 pour une publication non publiée', function (StatutPublication $statut) {
    $publication = Publication::factory()->create();
    $publication->forceFill(['statut' => $statut])->save();

    $this->get('/publication/'.$publication->slug)->assertNotFound()->assertSee('Page introuvable');
})->with([StatutPublication::Brouillon, StatutPublication::EnAttente, StatutPublication::Rejetee]);

it('renvoie 404 pour un slug inconnu ou une publication supprimée', function () {
    $publication = Publication::factory()->publiee()->create();
    $publication->delete();

    $this->get('/publication/'.$publication->slug)->assertNotFound();
    $this->get('/publication/inexistante')->assertNotFound();
});

it('laisse l’auteur et la modération voir une publication en attente', function () {
    $publication = Publication::factory()->enAttente()->create();
    $moderateur = Utilisateur::factory()->create();
    $moderateur->assignRole('moderateur');

    $this->actingAs($publication->auteur()->firstOrFail())->get('/publication/'.$publication->slug)
        ->assertOk()->assertSee('n’est pas visible dans le fil');
    $this->actingAs($moderateur)->get('/publication/'.$publication->slug)->assertOk();
    $this->actingAs(Utilisateur::factory()->create())->get('/publication/'.$publication->slug)->assertNotFound();
});

it('garde visible une publication clôturée, avec un bandeau', function () {
    $publication = Publication::factory()->publiee()->create();
    $publication->forceFill(['statut' => StatutPublication::Cloturee, 'motif_cloture' => 'Poste pourvu'])->save();

    $this->get('/publication/'.$publication->slug)->assertOk()->assertSee('Cette publication est clôturée : Poste pourvu.');
});

it('publie un JSON-LD JobPosting valide pour une offre d’emploi', function () {
    $entreprise = Entreprise::factory()->create(['nom' => 'Société Ivoirienne de Distribution', 'site_web' => 'https://sid.ci']);
    $publication = Publication::factory()->emploi()->publiee()->create(['entreprise_id' => $entreprise->id, 'titre' => 'Comptable confirmé']);
    DetailEmploi::factory()->create([
        'publication_id' => $publication->id,
        'type_contrat' => TypeContrat::Cdi,
        'salaire_min' => 350000,
        'salaire_max' => 400000,
        'salaire_a_negocier' => false,
        'date_limite' => now()->addDays(20),
        'mode_candidature' => ModeCandidature::Rezohelp,
    ]);

    $html = (string) $this->get('/publication/'.$publication->slug)->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $resultat);
    $jsonLd = json_decode($resultat[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

    expect($jsonLd['@context'])->toBe('https://schema.org/')
        ->and($jsonLd['@type'])->toBe('JobPosting')
        ->and($jsonLd['title'])->toBe('Comptable confirmé')
        ->and($jsonLd)->toHaveKeys(['description', 'datePosted', 'validThrough', 'hiringOrganization', 'jobLocation', 'employmentType'])
        ->and($jsonLd['employmentType'])->toBe('FULL_TIME')
        ->and($jsonLd['hiringOrganization']['name'])->toBe('Société Ivoirienne de Distribution')
        ->and($jsonLd['jobLocation']['address']['addressCountry'])->toBe('CI')
        ->and($jsonLd['baseSalary']['currency'])->toBe('XOF')
        ->and($jsonLd['baseSalary']['value']['minValue'])->toBe(350000)
        ->and($jsonLd['directApply'])->toBeTrue();
});

it('échappe le contenu du JSON-LD (pas d’injection de balise)', function () {
    $publication = Publication::factory()->emploi()->publiee()->create(['titre' => 'Offre </script><script>alert(1)</script>']);

    $html = (string) $this->get('/publication/'.$publication->slug)->getContent();

    expect($html)->not->toContain('</script><script>alert(1)');
});

it('n’ajoute pas de JSON-LD aux services et formations', function () {
    $publication = Publication::factory()->serviceOffre()->publiee()->create();

    $this->get('/publication/'.$publication->slug)->assertOk()->assertDontSee('application/ld+json', false);
});

it('fournit les balises Open Graph pour le partage WhatsApp', function () {
    $publication = Publication::factory()->publiee()->create(['titre' => 'Plombier disponible', 'extrait' => 'Dépannage rapide à Cocody.']);

    $this->get('/publication/'.$publication->slug)
        ->assertSee('<meta property="og:title" content="Plombier disponible">', false)
        ->assertSee('<meta property="og:description" content="Dépannage rapide à Cocody.">', false)
        ->assertSee('<meta property="og:url" content="'.route('publications.show', $publication->slug).'">', false)
        ->assertSee('property="og:image"', false);
});

it('affiche le téléphone d’un artisan seulement s’il l’a choisi, jamais celui d’un demandeur', function () {
    $offre = Publication::factory()->serviceOffre()->publiee()->create();
    DetailService::factory()->create(['publication_id' => $offre->id, 'telephone_contact' => '+2250506070809', 'afficher_telephone' => true]);

    $masquee = Publication::factory()->serviceOffre()->publiee()->create();
    DetailService::factory()->create(['publication_id' => $masquee->id, 'telephone_contact' => '+2250101010101', 'afficher_telephone' => false]);

    $demandeur = Utilisateur::factory()->create(['telephone' => '+2250708091011']);
    $demande = Publication::factory()->serviceDemande()->publiee()->create(['auteur_id' => $demandeur->id]);
    DetailService::factory()->demande()->create(['publication_id' => $demande->id]);

    $this->get('/publication/'.$offre->slug)->assertSee('tel:+2250506070809', false)->assertSee('05 06 07 08 09');
    $this->get('/publication/'.$masquee->slug)->assertDontSee('2250101010101')->assertDontSee('01 01 01 01 01');
    $this->get('/publication/'.$demande->slug)->assertOk()->assertDontSee('0708091011')->assertDontSee('07 08 09 10 11');
});

it('affiche un résumé et le lien vers l’annonce d’origine pour la veille', function () {
    $publication = Publication::factory()->veille()->publiee()->create();

    $this->get('/publication/'.$publication->slug)
        ->assertSee('Voir l’annonce')
        ->assertSee($publication->source_url, false);
});

it('redirige le lien court vers la page de la publication', function () {
    $publication = Publication::factory()->publiee()->create(['code_court' => 'ab12cd']);

    $this->get('/p/ab12cd')->assertStatus(301)->assertRedirect(route('publications.show', $publication->slug));
    $this->get('/p/zz99zz')->assertNotFound();
});

it('compte le partage et ouvre WhatsApp avec le titre et le lien court', function () {
    $publication = Publication::factory()->publiee()->create(['titre' => 'Électricien disponible', 'code_court' => 'xy34zt', 'nb_partages' => 0]);

    $reponse = $this->get('/publication/'.$publication->slug.'/partager');

    $reponse->assertRedirect('https://wa.me/?text='.rawurlencode('Électricien disponible — '.route('publications.court', 'xy34zt')));
    expect($publication->refresh()->nb_partages)->toBe(1);
});
