<?php

use App\Enums\StatutPublication;
use App\Jobs\CorrespondreAlertes;
use App\Models\Alerte;
use App\Models\Commune;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\RecapitulatifAlertes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $this->membre = Utilisateur::factory()->create();
    $this->alerte = Alerte::factory()->quotidienne()->create(['utilisateur_id' => $this->membre->id, 'commune_id' => $this->cocody->id, 'nom' => 'Cocody']);
});

function rapprocher(int $nombre, Commune $commune): Collection
{
    return collect(range(1, $nombre))->map(function (int $i) use ($commune): Publication {
        $publication = Publication::factory()->publiee(now()->subHours($i))->create(['commune_id' => $commune->id, 'titre' => "Offre numéro {$i} à Cocody"]);
        CorrespondreAlertes::dispatchSync($publication->id);

        return $publication;
    });
}

it('envoie un récapitulatif par alerte quotidienne avec les publications du jour', function () {
    rapprocher(3, $this->cocody);

    $this->artisan('alertes:recap-quotidien')->assertSuccessful()->expectsOutput('1 récapitulatif(s) envoyé(s).');

    Notification::assertSentTo($this->membre, RecapitulatifAlertes::class, function (RecapitulatifAlertes $notification): bool {
        $donnees = $notification->toArray($this->membre);

        return $notification->nombre === 3
            && $donnees['titre'] === '3 nouvelles publications pour « Cocody »'
            && str_contains($donnees['lien'], 'commune=cocody');
    });
    expect(DB::table('alertes_publications')->whereNull('notifie_le')->count())->toBe(0)
        ->and($this->alerte->refresh()->derniere_notification_le)->not->toBeNull();
});

it('n’envoie rien deux fois et rien sans nouvelle publication', function () {
    rapprocher(1, $this->cocody);

    $this->artisan('alertes:recap-quotidien');
    $this->artisan('alertes:recap-quotidien')->expectsOutput('0 récapitulatif(s) envoyé(s).');

    Notification::assertSentToTimes($this->membre, RecapitulatifAlertes::class, 1);
});

it('écarte les publications retirées du fil entre-temps', function () {
    $publications = rapprocher(2, $this->cocody);
    $publications->each(fn (Publication $p) => $p->forceFill(['statut' => StatutPublication::Cloturee])->save());

    $this->artisan('alertes:recap-quotidien')->expectsOutput('0 récapitulatif(s) envoyé(s).');

    Notification::assertNothingSent();
    expect(DB::table('alertes_publications')->whereNull('notifie_le')->count())->toBe(0);
});

it('ignore les alertes mises en pause', function () {
    rapprocher(1, $this->cocody);
    $this->alerte->forceFill(['active' => false])->save();

    $this->artisan('alertes:recap-quotidien');

    Notification::assertNothingSent();
});

it('est planifié chaque jour à 7h00', function () {
    $evenement = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'alertes:recap-quotidien'));

    expect($evenement)->not->toBeNull()
        ->and($evenement->expression)->toBe('0 7 * * *');
});
