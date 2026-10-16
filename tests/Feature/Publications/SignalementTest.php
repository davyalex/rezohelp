<?php

use App\Enums\MotifSignalement;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use App\Notifications\PublicationMasquee;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([ParametresSeeder::class, RolesEtPermissionsSeeder::class]);
    Notification::fake();
    $this->moderateur = Utilisateur::factory()->create();
    $this->moderateur->assignRole('moderateur');
    $this->publication = Publication::factory()->publiee()->create();
});

function signaler(Publication $publication, MotifSignalement $motif = MotifSignalement::Arnaque): void
{
    test()->actingAs(Utilisateur::factory()->create())
        ->post('/publication/'.$publication->id.'/signaler', ['motif' => $motif->value, 'commentaire' => 'Demande des frais'])
        ->assertRedirect(route('publications.show', $publication->slug));
}

it('enregistre le signalement d’un membre', function () {
    signaler($this->publication);

    $signalement = Signalement::query()->sole();
    expect($signalement->motif)->toBe(MotifSignalement::Arnaque)
        ->and($signalement->commentaire)->toBe('Demande des frais')
        ->and($signalement->traite_le)->toBeNull();
});

it('masque la publication au 3e signalement « arnaque » et prévient les modérateurs', function () {
    signaler($this->publication);
    signaler($this->publication);
    expect($this->publication->refresh()->statut)->toBe(StatutPublication::Publiee);
    Notification::assertNothingSent();

    signaler($this->publication);

    expect($this->publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
    Notification::assertSentTo($this->moderateur, PublicationMasquee::class);
    $this->get('/')->assertDontSee($this->publication->titre);
});

it('ne compte pas les autres motifs ni les signalements déjà traités', function () {
    signaler($this->publication, MotifSignalement::Doublon);
    signaler($this->publication, MotifSignalement::Inapproprie);
    Signalement::factory()->create(['publication_id' => $this->publication->id, 'motif' => MotifSignalement::Arnaque])
        ->forceFill(['traite_le' => now()])->save();
    signaler($this->publication);
    signaler($this->publication);

    expect($this->publication->refresh()->statut)->toBe(StatutPublication::Publiee);
});

it('suit le seuil réglé dans les paramètres', function () {
    Parametre::query()->whereKey('seuil_masquage_signalements')->firstOrFail()->update(['valeur' => '1']);

    signaler($this->publication);

    expect($this->publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
});

it('n’accepte qu’un signalement par membre et par publication', function () {
    $membre = Utilisateur::factory()->create();

    foreach (range(1, 3) as $i) {
        $this->actingAs($membre)->post('/publication/'.$this->publication->id.'/signaler', ['motif' => 'arnaque']);
    }

    expect(Signalement::query()->count())->toBe(1)
        ->and($this->publication->refresh()->statut)->toBe(StatutPublication::Publiee);
});

it('valide le motif', function () {
    $this->actingAs(Utilisateur::factory()->create())
        ->post('/publication/'.$this->publication->id.'/signaler', ['motif' => 'nimporte'])
        ->assertSessionHasErrors('motif');
});
