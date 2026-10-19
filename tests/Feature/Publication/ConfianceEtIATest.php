<?php

use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use App\Enums\ConfianceUtilisateur;
use App\Enums\MotifRejet;
use App\Enums\StatutAppelIa;
use App\Enums\StatutPublication;
use App\Jobs\EnrichirPublicationIA;
use App\Models\AppelIa;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use App\Notifications\PublicationRemiseEnAttente;
use Database\Seeders\ParametresSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(ParametresSeeder::class);
    Notification::fake();
    $this->auteur = Utilisateur::factory()->create();
});

function clientIaAvecScore(int $score, array $signaux = []): void
{
    app()->instance(ClientIA::class, new class($score, $signaux) implements ClientIA
    {
        public function __construct(private int $score, private array $signaux) {}

        public function enrichir(DemandeEnrichissement $demande): ResultatEnrichissement
        {
            return new ResultatEnrichissement('Texte', null, $this->score, $this->signaux, 'essai', 500, 100, 800);
        }
    });
}

function valider(Publication $publication): void
{
    $publication->forceFill(['statut' => StatutPublication::Publiee, 'publiee_le' => now(), 'tri_le' => now()])->save();
}

it('enregistre le score, complète les signaux et journalise l’appel avec son coût', function () {
    clientIaAvecScore(82, ['contact_whatsapp_uniquement']);
    $publication = Publication::factory()->enAttente()->create(['auteur_id' => $this->auteur->id, 'signaux_risque' => ['majuscules']]);

    EnrichirPublicationIA::dispatchSync($publication->id);

    $publication->refresh();
    $appel = AppelIa::query()->sole();
    expect($publication->score_fiabilite)->toBe(82)
        ->and($publication->signaux_risque)->toBe(['majuscules', 'contact_whatsapp_uniquement'])
        ->and($appel->statut)->toBe(StatutAppelIa::Succes)
        ->and($appel->tokens_entree)->toBe(500)
        ->and($appel->cout_estime_fcfa)->toBeGreaterThan(0);
});

it('remet en attente une publication en ligne dont le score tombe sous 40 et prévient l’auteur', function () {
    clientIaAvecScore(25, ['frais_demandes']);
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id]);

    EnrichirPublicationIA::dispatchSync($publication->id);

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
    Notification::assertSentTo($this->auteur, PublicationRemiseEnAttente::class);
});

it('laisse en ligne une publication dont le score reste correct', function () {
    clientIaAvecScore(55);
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id]);

    EnrichirPublicationIA::dispatchSync($publication->id);

    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee);
    Notification::assertNothingSent();
});

it('ignore les brouillons et journalise un échec définitif', function () {
    $brouillon = Publication::factory()->create(['auteur_id' => $this->auteur->id]);
    EnrichirPublicationIA::dispatchSync($brouillon->id);
    expect(AppelIa::query()->count())->toBe(0);

    (new EnrichirPublicationIA($brouillon->id))->failed(new RuntimeException('API indisponible'));
    expect(AppelIa::query()->sole()->statut)->toBe(StatutAppelIa::Echec);
});

it('rend un auteur fiable après 3 publications validées', function () {
    foreach (range(1, 3) as $i) {
        valider(Publication::factory()->enAttente()->create(['auteur_id' => $this->auteur->id]));
    }

    expect($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Fiable);
});

it('restreint un auteur après 2 rejets pour arnaque', function () {
    foreach (range(1, 2) as $i) {
        $publication = Publication::factory()->enAttente()->create(['auteur_id' => $this->auteur->id]);
        $publication->forceFill(['statut' => StatutPublication::Rejetee, 'motif_rejet' => MotifRejet::ArnaqueSuspectee])->save();
    }

    expect($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Restreint);
});

it('restreint un auteur dont un signalement a été retenu', function () {
    $premiere = Publication::factory()->enAttente()->create(['auteur_id' => $this->auteur->id]);
    Signalement::factory()->create(['publication_id' => $premiere->id, 'motif' => 'doublon'])->forceFill(['retenu' => true, 'traite_le' => now()])->save();

    valider($premiere);

    expect($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Restreint);
});

it('ne lève jamais seul une restriction', function () {
    $this->auteur->forceFill(['confiance' => ConfianceUtilisateur::Restreint])->save();

    foreach (range(1, 4) as $i) {
        valider(Publication::factory()->enAttente()->create(['auteur_id' => $this->auteur->id]));
    }

    expect($this->auteur->refresh()->confiance)->toBe(ConfianceUtilisateur::Restreint);
});
