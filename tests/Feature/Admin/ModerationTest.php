<?php

use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifRejet;
use App\Enums\StatutPublication;
use App\Events\PublicationPubliee;
use App\Models\JournalActivite;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationRejetee;
use App\Notifications\PublicationValidee;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->moderateur = Utilisateur::factory()->create();
    $this->moderateur->assignRole('moderateur');
    $this->auteur = Utilisateur::factory()->create();
});

function enAttente(Utilisateur $auteur, array $attributs = []): Publication
{
    return Publication::factory()->serviceOffre()->enAttente()->create(['auteur_id' => $auteur->id, ...$attributs]);
}

it('valide : publiée en tête du fil, alertes déclenchées, auteur notifié, journal écrit', function () {
    Event::fake([PublicationPubliee::class]);
    $publication = enAttente($this->auteur);

    $this->actingAs($this->moderateur)->post(route('admin.moderation.valider', $publication))->assertRedirect();

    $publication->refresh();
    expect($publication->statut)->toBe(StatutPublication::Publiee)
        ->and($publication->publiee_le)->not->toBeNull()
        ->and($publication->tri_le?->toDateTimeString())->toBe($publication->publiee_le?->toDateTimeString());
    Event::assertDispatched(PublicationPubliee::class, fn (PublicationPubliee $e): bool => $e->publication->is($publication));
    Notification::assertSentTo($this->auteur, PublicationValidee::class);
    expect(JournalActivite::query()->where('action', 'publication.validee')->where('sujet_id', $publication->id)->where('utilisateur_id', $this->moderateur->id)->exists())->toBeTrue();
});

it('propose la remontée dans la notification de validation d’une annonce d’artisan', function () {
    Produit::query()->where('code', 'remontee')->update(['actif' => true]);
    $publication = enAttente($this->auteur);

    $this->actingAs($this->moderateur)->post(route('admin.moderation.valider', $publication));

    Notification::assertSentTo($this->auteur, PublicationValidee::class, fn (PublicationValidee $n): bool => $n->remontee?->code === 'remontee'
        && str_contains((string) $n->toArray($this->auteur)['message'], 'Remontez votre annonce'));
});

it('remet en ligne une publication masquée sans la remonter en tête du fil', function () {
    $publication = Publication::factory()->serviceOffre()->publiee(now()->subDays(5))->create(['auteur_id' => $this->auteur->id]);
    $publication->forceFill(['statut' => StatutPublication::EnAttente])->save();

    $this->actingAs($this->moderateur)->post(route('admin.moderation.valider', $publication));

    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and($publication->tri_le?->isBefore(now()->subDays(4)))->toBeTrue();
});

it('rejette avec un motif : auteur notifié, motif conservé, journal écrit', function () {
    $publication = enAttente($this->auteur);

    $this->actingAs($this->moderateur)
        ->post(route('admin.moderation.rejeter', $publication), ['motif_rejet' => 'incomplete', 'motif_rejet_detail' => 'Précisez le tarif.'])
        ->assertRedirect();

    $publication->refresh();
    expect($publication->statut)->toBe(StatutPublication::Rejetee)
        ->and($publication->motif_rejet)->toBe(MotifRejet::Incomplete)
        ->and($publication->motif_rejet_detail)->toBe('Précisez le tarif.');
    Notification::assertSentTo($this->auteur, PublicationRejetee::class, fn (PublicationRejetee $n): bool => str_contains((string) $n->toArray($this->auteur)['message'], 'Précisez le tarif.'));
    expect(JournalActivite::query()->where('action', 'publication.rejetee')->sole()->donnees)->toMatchArray(['motif' => 'incomplete']);
});

it('exige une précision pour le motif « autre »', function () {
    $publication = enAttente($this->auteur);

    $this->actingAs($this->moderateur)->post(route('admin.moderation.rejeter', $publication), ['motif_rejet' => 'autre'])
        ->assertSessionHasErrors('motif_rejet_detail');

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
});

it('restreint l’auteur après deux rejets pour arnaque', function () {
    foreach ([enAttente($this->auteur), enAttente($this->auteur)] as $publication) {
        $this->actingAs($this->moderateur)->post(route('admin.moderation.rejeter', $publication), ['motif_rejet' => 'arnaque_suspectee']);
    }

    expect($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Restreint);
});

it('marque un doublon en citant l’originale', function () {
    $originale = Publication::factory()->serviceOffre()->publiee()->create(['auteur_id' => $this->auteur->id]);
    $publication = enAttente($this->auteur);

    $this->actingAs($this->moderateur)->post(route('admin.moderation.doublon', $publication), ['publication_originale_id' => $originale->id]);

    $publication->refresh();
    expect($publication->motif_rejet)->toBe(MotifRejet::Doublon)
        ->and($publication->motif_rejet_detail)->toContain('n° '.$originale->id)
        ->and(JournalActivite::query()->where('action', 'publication.doublon')->exists())->toBeTrue();

    // Une publication n'est jamais le doublon d'elle-même.
    $autre = enAttente($this->auteur);
    $this->post(route('admin.moderation.doublon', $autre), ['publication_originale_id' => $autre->id])->assertSessionHas('erreur');
});

it('passe automatiquement à la publication suivante de la file, la plus risquée d’abord', function () {
    $sure = enAttente($this->auteur, ['score_fiabilite' => 90]);
    $risquee = enAttente($this->auteur, ['score_fiabilite' => 20]);
    $courante = enAttente($this->auteur, ['score_fiabilite' => 50]);

    $this->actingAs($this->moderateur)->post(route('admin.moderation.valider', $courante))
        ->assertRedirect(route('admin.moderation.show', $risquee));
    $this->post(route('admin.moderation.valider', $risquee))->assertRedirect(route('admin.moderation.show', $sure));
    $this->post(route('admin.moderation.valider', $sure))->assertRedirect(route('admin.moderation.index'));
});

it('refuse de décider sur une publication qui n’est plus en attente', function () {
    $publication = Publication::factory()->publiee()->create();

    $this->actingAs($this->moderateur)->post(route('admin.moderation.valider', $publication))->assertSessionHas('erreur');
    $this->post(route('admin.moderation.rejeter', $publication), ['motif_rejet' => 'incomplete'])->assertSessionHas('erreur');
});

it('valide ou rejette en lot, chacune avec ses notifications, en ignorant celles déjà traitées', function () {
    $publications = collect(range(1, 3))->map(fn () => enAttente($this->auteur));
    $dejaPubliee = Publication::factory()->publiee()->create();

    $this->actingAs($this->moderateur)
        ->post(route('admin.moderation.lot'), ['action' => 'valider', 'ids' => [...$publications->pluck('id'), $dejaPubliee->id]])
        ->assertSessionHas('statut', '3 publication(s) validée(s).');

    Notification::assertSentToTimes($this->auteur, PublicationValidee::class, 3);
    expect(JournalActivite::query()->where('action', 'publication.validee')->count())->toBe(3);

    $aRejeter = collect(range(1, 2))->map(fn () => enAttente($this->auteur));
    $this->post(route('admin.moderation.lot'), ['action' => 'rejeter', 'motif_rejet' => 'hors_zone', 'ids' => $aRejeter->pluck('id')->all()]);

    expect(Publication::query()->whereKey($aRejeter->pluck('id'))->where('motif_rejet', 'hors_zone')->count())->toBe(2);
    $this->post(route('admin.moderation.lot'), ['action' => 'rejeter', 'ids' => [1]])->assertSessionHasErrors('motif_rejet');
});

it('cherche une publication par titre ou numéro pour Select2', function () {
    $publication = Publication::factory()->create(['titre' => 'Plombier expérimenté Yopougon']);

    $this->actingAs($this->moderateur)->getJson(route('admin.recherche.publications', ['q' => 'Plombier']))
        ->assertOk()->assertJsonPath('results.0.id', $publication->id);
    $this->getJson(route('admin.recherche.publications', ['q' => (string) $publication->id]))->assertJsonPath('results.0.id', $publication->id);
});
