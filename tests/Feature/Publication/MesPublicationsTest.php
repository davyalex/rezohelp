<?php

use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifRejet;
use App\Enums\StatutPublication;
use App\Jobs\EnrichirPublicationIA;
use App\Models\DetailService;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    referentielsPublication();
    Queue::fake([EnrichirPublicationIA::class]);
    $this->auteur = Utilisateur::factory()->create();
});

/**
 * Offre de service publiée par l'auteur courant, avec ses détails.
 */
function offrePubliee(Utilisateur $auteur): Publication
{
    test()->actingAs($auteur)->post('/publier/service-offre', donneesServiceOffre())->assertSessionHasNoErrors();
    $publication = Publication::query()->latest('id')->firstOrFail();
    $publication->forceFill(['statut' => StatutPublication::Publiee, 'publiee_le' => now(), 'tri_le' => now()])->save();

    return $publication->refresh();
}

it('liste les publications de l’auteur avec statut, motif de rejet et statistiques', function () {
    $rejetee = Publication::factory()->create(['auteur_id' => $this->auteur->id, 'titre' => 'Offre refusée par la modération', 'nb_vues' => 42]);
    $rejetee->forceFill(['statut' => StatutPublication::Rejetee, 'motif_rejet' => MotifRejet::Incomplete, 'motif_rejet_detail' => 'Précisez le salaire'])->save();
    Publication::factory()->create(['titre' => 'Publication d’un autre membre']);

    $this->actingAs($this->auteur)->get('/mon-espace/publications')
        ->assertOk()
        ->assertSee('Offre refusée par la modération')
        ->assertSee('Annonce incomplète')
        ->assertSee('Précisez le salaire')
        ->assertSee('42 vues')
        ->assertSee('Corriger et renvoyer')
        ->assertDontSee('Publication d’un autre membre');
});

it('renvoie en attente une modification sensible d’un auteur nouveau', function () {
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)
        ->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['titre' => 'Électricien qualifié, dépannage 7 j / 7']))
        ->assertRedirect(route('espace.publications.index'));

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->titre)->toBe('Électricien qualifié, dépannage 7 j / 7');
});

it('renvoie en attente un changement de numéro de contact d’un auteur nouveau', function () {
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['telephone_contact' => '01 02 03 04 05']));

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
});

it('garde en ligne une modification non sensible d’un auteur nouveau', function () {
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['tarif_indicatif' => 7000]));

    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and(DetailService::query()->find($publication->id)?->tarif_indicatif)->toBe(7000);
});

it('garde en ligne la modification d’un auteur fiable et relance l’analyse de risque', function () {
    $publication = offrePubliee($this->auteur);
    $this->auteur->forceFill(['confiance' => ConfianceUtilisateur::Fiable])->save();

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['titre' => 'Électricien qualifié, dépannage 7 j / 7']));
    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee);
    Queue::assertPushed(EnrichirPublicationIA::class);

    $this->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['contenu' => contenuValide('Paiement par Western Union uniquement.')]));
    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->signaux_risque)->toContain('expression_a_risque');
});

it('ne repousse pas l’expiration lors d’une modification', function () {
    $publication = offrePubliee($this->auteur);
    $expiration = $publication->expire_le;
    $this->travel(5)->days();

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['tarif_indicatif' => 6000]));

    expect($publication->refresh()->expire_le?->toDateTimeString())->toBe($expiration?->toDateTimeString());
});

it('renvoie en attente une publication rejetée une fois corrigée', function () {
    $publication = offrePubliee($this->auteur);
    $publication->forceFill(['statut' => StatutPublication::Rejetee, 'motif_rejet' => MotifRejet::Incomplete])->save();

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['tarif_indicatif' => 6000]));

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->motif_rejet)->toBeNull();
});

it('publie un brouillon comme une nouvelle publication', function () {
    $this->actingAs($this->auteur)->post('/publier/service-offre', donneesServiceOffre(['action' => 'brouillon']));
    $brouillon = Publication::query()->sole();

    $this->get('/mon-espace/publications/'.$brouillon->id.'/modifier')->assertOk()->assertSee('Électricien disponible à Cocody');
    $this->put('/mon-espace/publications/'.$brouillon->id.'/modifier', donneesServiceOffre())
        ->assertRedirect(route('publier.confirmation', $brouillon->id));

    expect($brouillon->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and(Publication::query()->count())->toBe(1);
});

it('clôture une publication : elle quitte le fil mais sa page reste visible', function () {
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)->post('/mon-espace/publications/'.$publication->id.'/cloturer', ['motif_cloture' => 'Plus disponible'])->assertRedirect();

    expect($publication->refresh()->statut)->toBe(StatutPublication::Cloturee)
        ->and($publication->motif_cloture)->toBe('Plus disponible');
    $this->get('/')->assertDontSee($publication->titre);
    $this->get('/publication/'.$publication->slug)->assertOk()->assertSee('Plus disponible');
});

it('prolonge de 30 jours, trois fois au maximum', function () {
    $publication = offrePubliee($this->auteur);
    $expiration = $publication->expire_le;
    $this->actingAs($this->auteur);

    foreach (range(1, 3) as $fois) {
        $this->post('/mon-espace/publications/'.$publication->id.'/prolonger')->assertSessionHasNoErrors();
    }
    $this->post('/mon-espace/publications/'.$publication->id.'/prolonger')->assertSessionHasErrors('prolongation');

    $publication->refresh();
    expect($publication->nb_prolongations)->toBe(3)
        ->and((int) round($expiration?->diffInDays($publication->expire_le, true) ?? 0))->toBe(90);
});

it('suit le nombre maximal de prolongations réglé dans les paramètres', function () {
    parametre('max_prolongations', '1');
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)->post('/mon-espace/publications/'.$publication->id.'/prolonger');
    $this->post('/mon-espace/publications/'.$publication->id.'/prolonger')->assertSessionHasErrors('prolongation');
});

it('duplique une publication en brouillon, détails compris', function () {
    $publication = offrePubliee($this->auteur);

    $reponse = $this->actingAs($this->auteur)->post('/mon-espace/publications/'.$publication->id.'/dupliquer');

    $copie = Publication::query()->whereKeyNot($publication->id)->with('detailService')->sole();
    $reponse->assertRedirect(route('espace.publications.edit', $copie->id));
    expect($copie->statut)->toBe(StatutPublication::Brouillon)
        ->and($copie->titre)->toBe($publication->titre)
        ->and($copie->slug)->not->toBe($publication->slug)
        ->and($copie->code_court)->not->toBe($publication->code_court)
        ->and($copie->nb_vues)->toBe(0)
        ->and($copie->detailService?->telephone_contact)->toBe('+2250506070809');
});

it('supprime une publication en la gardant en base (soft delete)', function () {
    $publication = offrePubliee($this->auteur);

    $this->actingAs($this->auteur)->delete('/mon-espace/publications/'.$publication->id)->assertRedirect(route('espace.publications.index'));

    expect(Publication::query()->find($publication->id))->toBeNull()
        ->and(Publication::withTrashed()->find($publication->id))->not->toBeNull();
});

it('interdit toute action sur la publication d’un autre membre', function (string $methode, string $suffixe) {
    $publication = offrePubliee($this->auteur);
    $intrus = Utilisateur::factory()->create();

    $this->actingAs($intrus)->{$methode}('/mon-espace/publications/'.$publication->id.$suffixe, donneesServiceOffre(['motif_cloture' => 'Complet']))
        ->assertForbidden();

    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and(Publication::query()->count())->toBe(1);
})->with([
    'modifier (formulaire)' => ['get', '/modifier'],
    'modifier' => ['put', '/modifier'],
    'clôturer' => ['post', '/cloturer'],
    'prolonger' => ['post', '/prolonger'],
    'dupliquer' => ['post', '/dupliquer'],
    'supprimer' => ['delete', ''],
    'réponses' => ['get', '/reponses'],
]);

it('n’autorise pas la modification d’une publication clôturée ou expirée', function () {
    $publication = offrePubliee($this->auteur);
    $publication->forceFill(['statut' => StatutPublication::Expiree])->save();

    $this->actingAs($this->auteur)->get('/mon-espace/publications/'.$publication->id.'/modifier')->assertForbidden();
});

it('remplace les photos en modification sans dépasser la limite du plan', function () {
    Storage::fake('public');
    $publication = offrePubliee($this->auteur);
    $photo = fn (string $nom) => UploadedFile::fake()->image($nom, 800, 600);

    $this->actingAs($this->auteur)->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['photos' => [$photo('a.jpg'), $photo('b.jpg'), $photo('c.jpg')]]));
    $this->put('/mon-espace/publications/'.$publication->id.'/modifier', donneesServiceOffre(['photos' => [$photo('d.jpg'), $photo('e.jpg')]]));

    expect($publication->refresh()->getMedia('photos')->pluck('file_name')->all())->toBe(['d.jpg', 'e.jpg'])
        ->and($publication->image_url)->toContain('d');
});
