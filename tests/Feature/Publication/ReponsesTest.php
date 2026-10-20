<?php

use App\Enums\ModeCandidature;
use App\Enums\ModeInscription;
use App\Enums\StatutPublication;
use App\Enums\StatutReponse;
use App\Enums\TypeReponse;
use App\Models\DetailEmploi;
use App\Models\DetailFormation;
use App\Models\DetailService;
use App\Models\PlanLimite;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use App\Notifications\NouvelleReponse;
use App\Notifications\StatutReponseModifie;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->auteur = Utilisateur::factory()->create();
    $this->candidat = Utilisateur::factory()->create(['telephone' => '+2250102030405']);
});

function offreEmploi(Utilisateur $auteur, ModeCandidature $mode = ModeCandidature::Rezohelp, ?string $contact = null): Publication
{
    $publication = Publication::factory()->emploi()->publiee()->create(['auteur_id' => $auteur->id, 'nb_reponses' => 0]);
    DetailEmploi::factory()->create(['publication_id' => $publication->id, 'mode_candidature' => $mode, 'contact_candidature' => $contact]);

    return $publication;
}

it('enregistre une candidature, incrémente le compteur et prévient l’auteur', function () {
    $offre = offreEmploi($this->auteur);

    $this->actingAs($this->candidat)
        ->post('/publication/'.$offre->slug.'/repondre', ['message' => 'Bonjour, je suis disponible.', 'partager_telephone' => '1'])
        ->assertRedirect(route('publications.show', $offre->slug).'#repondre');

    $reponse = Reponse::query()->sole();
    expect($reponse->type)->toBe(TypeReponse::Candidature)
        ->and($reponse->statut)->toBe(StatutReponse::Envoyee)
        ->and($reponse->telephone_partage)->toBeTrue()
        ->and($offre->refresh()->nb_reponses)->toBe(1);
    Notification::assertSentTo($this->auteur, NouvelleReponse::class);

    $this->get('/publication/'.$offre->slug)->assertSee('Vous avez déjà répondu');
});

it('n’accepte qu’une réponse par personne et par publication', function () {
    $offre = offreEmploi($this->auteur);
    $this->actingAs($this->candidat);

    $this->post('/publication/'.$offre->slug.'/repondre', ['message' => 'Premier envoi'])->assertSessionHasNoErrors();
    $this->post('/publication/'.$offre->slug.'/repondre', ['message' => 'Second envoi'])->assertSessionHasErrors('message');

    expect(Reponse::query()->count())->toBe(1)
        ->and($offre->refresh()->nb_reponses)->toBe(1);
});

it('interdit à l’auteur de répondre à sa propre publication et exige d’être connecté', function () {
    $offre = offreEmploi($this->auteur);

    $this->post('/publication/'.$offre->slug.'/repondre')->assertRedirect(route('login'));
    $this->actingAs($this->auteur)->post('/publication/'.$offre->slug.'/repondre')->assertForbidden();
});

it('refuse une réponse à une publication non publiée', function () {
    $offre = offreEmploi($this->auteur);
    $offre->forceFill(['statut' => StatutPublication::Cloturee])->save();

    $this->actingAs($this->candidat)->post('/publication/'.$offre->slug.'/repondre')->assertForbidden();
});

it('n’accepte pas de réponse Rezohelp quand l’offre se contacte autrement, mais compte le clic', function () {
    $offre = offreEmploi($this->auteur, ModeCandidature::Lien, 'https://carrieres.exemple.ci/offre/12');

    $this->actingAs($this->candidat)->post('/publication/'.$offre->slug.'/repondre')->assertSessionHasErrors('message');

    $this->get('/publication/'.$offre->slug.'/contact')->assertRedirect('https://carrieres.exemple.ci/offre/12');
    expect($offre->refresh()->nb_reponses)->toBe(1);
});

it('ouvre l’e-mail ou le téléphone du recruteur', function () {
    $email = offreEmploi($this->auteur, ModeCandidature::Email, 'rh@exemple.ci');
    $telephone = offreEmploi(Utilisateur::factory()->create(), ModeCandidature::Telephone, '+2250708091011');

    expect($this->get('/publication/'.$email->slug.'/contact')->headers->get('Location'))->toStartWith('mailto:rh@exemple.ci?subject=');
    $this->get('/publication/'.$telephone->slug.'/contact')->assertRedirect('tel:+2250708091011');
});

it('partage toujours le numéro pour « Je suis disponible » et donne les coordonnées au demandeur', function () {
    $demande = Publication::factory()->serviceDemande()->publiee()->create(['auteur_id' => $this->auteur->id]);
    DetailService::factory()->demande()->create(['publication_id' => $demande->id]);
    $artisan = Utilisateur::factory()->create(['telephone' => '+2250506070809']);

    $this->actingAs($artisan)->post('/publication/'.$demande->slug.'/repondre', ['message' => 'Disponible demain, 10 000 FCFA.', 'partager_telephone' => '0']);

    $reponse = Reponse::query()->sole();
    expect($reponse->type)->toBe(TypeReponse::Proposition)
        ->and($reponse->telephone_partage)->toBeTrue();

    $this->actingAs($this->auteur)->get('/mon-espace/publications/'.$demande->id.'/reponses')
        ->assertOk()
        ->assertSee('Disponible demain, 10 000 FCFA.')
        ->assertSee('05 06 07 08 09');
});

it('limite « Je suis disponible » à reponses.demandes_mois quand les quotas sont actifs', function () {
    parametre('quotas_actifs', '1');
    PlanLimite::query()->where('cle', 'reponses.demandes_mois')->update(['valeur' => '1']);

    foreach (range(1, 2) as $i) {
        $demande = Publication::factory()->serviceDemande()->publiee()->create();
        DetailService::factory()->demande()->create(['publication_id' => $demande->id]);
        $reponses[] = $this->actingAs($this->candidat)->post('/publication/'.$demande->slug.'/repondre', ['message' => 'Disponible']);
    }

    $reponses[1]->assertSessionHasErrors(['message' => 'Vous avez atteint votre nombre de réponses aux demandes pour ce mois-ci.']);
    expect(Reponse::query()->count())->toBe(1);
});

it('propose un formulaire de contact quand l’artisan masque son numéro', function () {
    $offre = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->auteur->id]);
    DetailService::factory()->create(['publication_id' => $offre->id, 'afficher_telephone' => false]);

    $this->actingAs($this->candidat)->get('/publication/'.$offre->slug)->assertSee('Contacter');
    $this->post('/publication/'.$offre->slug.'/repondre', ['message' => 'Pouvez-vous passer jeudi ?']);

    expect(Reponse::query()->sole()->type)->toBe(TypeReponse::Contact);
});

it('appelle directement l’artisan qui affiche son numéro, sans formulaire', function () {
    $offre = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->auteur->id]);
    DetailService::factory()->create(['publication_id' => $offre->id, 'afficher_telephone' => true, 'telephone_contact' => '+2250506070809']);

    $this->actingAs($this->candidat)->get('/publication/'.$offre->slug)->assertSee('tel:+2250506070809', false);
    $this->post('/publication/'.$offre->slug.'/repondre')->assertSessionHasErrors('message');
});

it('enregistre une inscription à une formation', function () {
    $formation = Publication::factory()->formation()->publiee()->create(['auteur_id' => $this->auteur->id]);
    DetailFormation::factory()->create(['publication_id' => $formation->id, 'mode_inscription' => ModeInscription::Rezohelp]);

    $this->actingAs($this->candidat)->post('/publication/'.$formation->slug.'/repondre', ['partager_telephone' => '0']);

    $reponse = Reponse::query()->sole();
    expect($reponse->type)->toBe(TypeReponse::Inscription)
        ->and($reponse->telephone_partage)->toBeFalse();
});

it('ne montre le numéro du candidat à l’auteur que s’il l’a partagé', function () {
    $offre = offreEmploi($this->auteur);
    $this->actingAs($this->candidat)->post('/publication/'.$offre->slug.'/repondre', ['message' => 'Bonjour', 'partager_telephone' => '0']);

    $this->actingAs($this->auteur)->get('/mon-espace/publications/'.$offre->id.'/reponses')
        ->assertOk()
        ->assertSee('Numéro non partagé.')
        ->assertDontSee('01 02 03 04 05');
});

it('marque une réponse vue, retenue ou refusée et prévient le répondant', function () {
    $offre = offreEmploi($this->auteur);
    $this->actingAs($this->candidat)->post('/publication/'.$offre->slug.'/repondre');
    $reponse = Reponse::query()->sole();

    $this->actingAs($this->auteur)->patch('/mon-espace/reponses/'.$reponse->id, ['statut' => 'retenue'])->assertRedirect();

    expect($reponse->refresh()->statut)->toBe(StatutReponse::Retenue);
    Notification::assertSentTo($this->candidat, StatutReponseModifie::class);

    $this->patch('/mon-espace/reponses/'.$reponse->id, ['statut' => 'envoyee'])->assertSessionHasErrors('statut');
});

it('interdit à un autre membre de traiter les réponses d’une publication', function () {
    $offre = offreEmploi($this->auteur);
    $this->actingAs($this->candidat)->post('/publication/'.$offre->slug.'/repondre');
    $reponse = Reponse::query()->sole();

    $this->actingAs(Utilisateur::factory()->create())->patch('/mon-espace/reponses/'.$reponse->id, ['statut' => 'refusee'])->assertForbidden();
    $this->actingAs($this->candidat)->patch('/mon-espace/reponses/'.$reponse->id, ['statut' => 'retenue'])->assertForbidden();

    expect($reponse->refresh()->statut)->toBe(StatutReponse::Envoyee);
});
