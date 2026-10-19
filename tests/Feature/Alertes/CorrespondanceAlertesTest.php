<?php

use App\Enums\FrequenceAlerte;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Events\PublicationPubliee;
use App\Jobs\CorrespondreAlertes;
use App\Jobs\EnrichirPublicationIA;
use App\Models\Alerte;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\AlerteCorrespondante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $this->yopougon = Commune::query()->where('slug', 'yopougon')->firstOrFail();
    $this->batiment = Categorie::query()->where('slug', 'service-batiment-et-reparations')->firstOrFail();
    $this->electricite = Categorie::query()->where('slug', 'service-electricite')->firstOrFail();
    $this->plomberie = Categorie::query()->where('slug', 'service-plomberie')->firstOrFail();
});

function alertePour(array $criteres = [], ?Utilisateur $membre = null): Alerte
{
    return Alerte::factory()->create(array_merge(['utilisateur_id' => ($membre ?? Utilisateur::factory()->create())->id], $criteres));
}

function publierEtCorrespondre(array $attributs = []): Publication
{
    $publication = Publication::factory()->publiee()->create(array_merge([
        'titre' => 'Électricien disponible à Cocody',
        'contenu' => 'Dépannage électrique, installation de prises et de compteurs, interventions rapides.',
    ], $attributs));
    CorrespondreAlertes::dispatchSync($publication->id);

    return $publication;
}

it('notifie les alertes dont tous les critères correspondent', function () {
    $correspond = alertePour(['type' => TypePublication::Service, 'categorie_id' => $this->electricite->id, 'commune_id' => $this->cocody->id]);
    $autreCommune = alertePour(['type' => TypePublication::Service, 'commune_id' => $this->yopougon->id]);
    $autreType = alertePour(['type' => TypePublication::Emploi]);
    $autreCategorie = alertePour(['categorie_id' => $this->plomberie->id]);

    $publication = publierEtCorrespondre([
        'type' => TypePublication::Service, 'sous_type' => 'offre',
        'categorie_id' => $this->electricite->id, 'commune_id' => $this->cocody->id,
    ]);

    Notification::assertSentTo($correspond->utilisateur, AlerteCorrespondante::class, fn (AlerteCorrespondante $n): bool => $n->publication->is($publication));
    Notification::assertNotSentTo([$autreCommune->utilisateur, $autreType->utilisateur, $autreCategorie->utilisateur], AlerteCorrespondante::class);
    expect(DB::table('alertes_publications')->where('alerte_id', $correspond->id)->value('notifie_le'))->not->toBeNull();
});

it('couvre les sous-catégories d’une catégorie principale', function () {
    $alerte = alertePour(['categorie_id' => $this->batiment->id]);

    publierEtCorrespondre(['type' => TypePublication::Service, 'categorie_id' => $this->plomberie->id]);

    Notification::assertSentTo($alerte->utilisateur, AlerteCorrespondante::class);
});

it('filtre par type de contrat', function () {
    $cdi = alertePour(['type' => TypePublication::Emploi, 'type_contrat' => TypeContrat::Cdi]);
    $stage = alertePour(['type' => TypePublication::Emploi, 'type_contrat' => TypeContrat::Stage]);

    publierEtCorrespondre(['type' => TypePublication::Emploi, 'type_contrat' => TypeContrat::Cdi, 'titre' => 'Comptable confirmé']);

    Notification::assertSentTo($cdi->utilisateur, AlerteCorrespondante::class);
    Notification::assertNotSentTo($stage->utilisateur, AlerteCorrespondante::class);
});

it('exige tous les mots-clés, sans tenir compte des accents ni de la casse', function () {
    $tousPresents = alertePour(['mots_cles' => 'electricien COMPTEURS']);
    $unManquant = alertePour(['mots_cles' => 'électricien plomberie']);
    $debutDeMot = alertePour(['mots_cles' => 'electri']);

    publierEtCorrespondre();

    Notification::assertSentTo([$tousPresents->utilisateur, $debutDeMot->utilisateur], AlerteCorrespondante::class);
    Notification::assertNotSentTo($unManquant->utilisateur, AlerteCorrespondante::class);
});

it('ignore les alertes en pause, celles de l’auteur et des comptes suspendus', function () {
    $pause = alertePour(['commune_id' => $this->cocody->id, 'active' => false]);
    $auteur = Utilisateur::factory()->create();
    $alerteAuteur = alertePour(['commune_id' => $this->cocody->id], $auteur);
    $suspendu = Utilisateur::factory()->suspendu()->create();
    alertePour(['commune_id' => $this->cocody->id], $suspendu);

    publierEtCorrespondre(['commune_id' => $this->cocody->id, 'auteur_id' => $auteur->id]);

    Notification::assertNothingSent();
    expect(DB::table('alertes_publications')->count())->toBe(0)
        ->and($pause->refresh()->derniere_notification_le)->toBeNull()
        ->and($alerteAuteur->refresh()->derniere_notification_le)->toBeNull();
});

it('notifie de nouveau un membre dont la suspension temporaire est terminée', function () {
    $ancienSuspendu = Utilisateur::factory()->create();
    $ancienSuspendu->forceFill(['suspendu_le' => now()->subDays(10), 'suspendu_jusqu_au' => now()->subDay()])->save();
    alertePour(['commune_id' => $this->cocody->id], $ancienSuspendu);

    publierEtCorrespondre(['commune_id' => $this->cocody->id]);

    Notification::assertSentTo($ancienSuspendu, AlerteCorrespondante::class);
});

it('ne notifie jamais deux fois la même publication', function () {
    $alerte = alertePour(['commune_id' => $this->cocody->id]);
    $publication = publierEtCorrespondre(['commune_id' => $this->cocody->id]);

    // Dépubliée puis republiée : la correspondance est relancée, sans nouvelle notification.
    CorrespondreAlertes::dispatchSync($publication->id);
    CorrespondreAlertes::dispatchSync($publication->id);

    Notification::assertSentToTimes($alerte->utilisateur, AlerteCorrespondante::class, 1);
    expect(DB::table('alertes_publications')->count())->toBe(1);
});

it('ne notifie qu’une fois un membre dont plusieurs alertes correspondent', function () {
    $membre = Utilisateur::factory()->create();
    alertePour(['commune_id' => $this->cocody->id], $membre);
    alertePour(['type' => TypePublication::Service], $membre);

    publierEtCorrespondre(['type' => TypePublication::Service, 'commune_id' => $this->cocody->id]);

    Notification::assertSentToTimes($membre, AlerteCorrespondante::class, 1);
    expect(DB::table('alertes_publications')->count())->toBe(2);
});

it('garde les correspondances des alertes quotidiennes pour le récapitulatif', function () {
    $quotidienne = alertePour(['commune_id' => $this->cocody->id, 'frequence' => FrequenceAlerte::Quotidienne]);

    publierEtCorrespondre(['commune_id' => $this->cocody->id]);

    Notification::assertNothingSent();
    expect(DB::table('alertes_publications')->where('alerte_id', $quotidienne->id)->value('notifie_le'))->toBeNull();
});

it('ignore une publication qui n’est plus en ligne', function () {
    alertePour(['commune_id' => $this->cocody->id]);
    $publication = Publication::factory()->enAttente()->create(['commune_id' => $this->cocody->id]);

    CorrespondreAlertes::dispatchSync($publication->id);

    Notification::assertNothingSent();
});

it('lance la correspondance quand une publication est publiée', function () {
    $alerte = alertePour(['type' => TypePublication::Service, 'commune_id' => $this->cocody->id]);
    $publication = Publication::factory()->publiee()->create(['type' => TypePublication::Service, 'commune_id' => $this->cocody->id]);

    PublicationPubliee::dispatch($publication);

    Notification::assertSentTo($alerte->utilisateur, AlerteCorrespondante::class);
    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee);
});

it('notifie les alertes quand un membre fiable publie directement', function () {
    Queue::fake([EnrichirPublicationIA::class]);
    $alerte = alertePour(['type' => TypePublication::Service, 'categorie_id' => $this->electricite->id]);
    $artisan = Utilisateur::factory()->fiable()->create();
    Publication::factory()->publiee()->create(['auteur_id' => $artisan->id]);

    $this->actingAs($artisan)->post('/publier/service-offre', donneesServiceOffre())->assertSessionHasNoErrors();

    Notification::assertSentTo($alerte->utilisateur, AlerteCorrespondante::class);
});
