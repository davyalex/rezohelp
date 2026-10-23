<?php

use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifSignalement;
use App\Enums\StatutPublication;
use App\Models\Administrateur;
use App\Models\JournalActivite;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->moderateur = Administrateur::factory()->moderateur()->create();
    $this->auteur = Utilisateur::factory()->create();
    $this->publication = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id]);

    // Trois signalements « arnaque » : la publication est masquée automatiquement.
    foreach (range(1, 3) as $i) {
        Signalement::query()->create(['publication_id' => $this->publication->id, 'utilisateur_id' => Utilisateur::factory()->create()->id, 'motif' => MotifSignalement::Arnaque]);
    }
});

it('liste les signalements ouverts groupés par publication', function () {
    $reponse = $this->actingAs($this->moderateur, 'admin')
        ->getJson(route('admin.signalements.index', ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    expect($reponse->json('recordsTotal'))->toBe(1)
        ->and($reponse->json('data.0.nb_signalements'))->toBe(3)
        ->and($reponse->json('data.0.motifs'))->toBe('Arnaque');
});

it('rétablit une publication masquée : signalements classés, non retenus', function () {
    expect($this->publication->refresh()->statut)->toBe(StatutPublication::EnAttente);

    $this->actingAs($this->moderateur, 'admin')->post(route('admin.signalements.decider', $this->publication->id), ['decision' => 'retablir'])
        ->assertRedirect(route('admin.signalements.index'));

    expect($this->publication->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and(Signalement::query()->whereNull('traite_le')->count())->toBe(0)
        ->and(Signalement::query()->where('retenu', true)->count())->toBe(0)
        ->and(Signalement::query()->value('traite_par'))->toBe($this->moderateur->id)
        ->and($this->auteur->refresh()->confiance)->not->toBe(ConfianceUtilisateur::Restreint);
});

it('supprime la publication et retient les signalements : l’auteur devient restreint', function () {
    $this->actingAs($this->moderateur, 'admin')->post(route('admin.signalements.decider', $this->publication->id), ['decision' => 'supprimer']);

    expect($this->publication->refresh()->trashed())->toBeTrue()
        ->and(Signalement::query()->where('retenu', true)->count())->toBe(3)
        ->and($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Restreint)
        ->and($this->auteur->estSuspendu())->toBeFalse()
        ->and(JournalActivite::query()->where('action', 'signalements.traites')->sole()->donnees)->toMatchArray(['decision' => 'supprimer', 'signalements' => 3]);
});

it('supprime et suspend l’auteur définitivement', function () {
    $autre = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id]);

    $this->actingAs($this->moderateur, 'admin')->post(route('admin.signalements.decider', $this->publication->id), ['decision' => 'supprimer_et_suspendre']);

    $this->auteur->refresh();
    expect($this->auteur->estSuspendu())->toBeTrue()
        ->and($this->auteur->suspendu_jusqu_au)->toBeNull()
        ->and($autre->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and(JournalActivite::query()->where('action', 'utilisateur.suspendu')->exists())->toBeTrue();
});

it('refuse une décision inconnue', function () {
    $this->actingAs($this->moderateur, 'admin')->post(route('admin.signalements.decider', $this->publication->id), ['decision' => 'ignorer'])
        ->assertSessionHasErrors('decision');
});
