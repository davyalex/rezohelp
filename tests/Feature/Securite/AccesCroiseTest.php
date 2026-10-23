<?php

use App\Enums\StatutCommande;
use App\Enums\StatutReponse;
use App\Models\Alerte;
use App\Models\Commande;
use App\Models\Commentaire;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use Illuminate\Support\Str;

/**
 * Lot 9 : un membre ne peut ni voir ni modifier les ressources d'un autre (CONTEXTE §16).
 * Chaque route « Mon espace » qui désigne une ressource est essayée par un intrus connecté.
 */
beforeEach(function () {
    referentielsPublication();

    $this->proprietaire = Utilisateur::factory()->create();
    $this->intrus = Utilisateur::factory()->create();

    $this->publication = Publication::factory()->emploi()->publiee()->create(['auteur_id' => $this->proprietaire->id, 'titre' => 'Offre du propriétaire']);
    $this->reponse = Reponse::factory()->create(['publication_id' => $this->publication->id]);
    $this->alerte = Alerte::factory()->create(['utilisateur_id' => $this->proprietaire->id, 'nom' => 'Alerte du propriétaire']);
    $this->commentaire = Commentaire::factory()->create(['utilisateur_id' => $this->proprietaire->id, 'publication_id' => $this->publication->id]);
    $this->commande = Commande::factory()->create(['utilisateur_id' => $this->proprietaire->id, 'statut' => StatutCommande::Payee, 'numero_recu' => 'REC-2026-000001', 'payee_le' => now()]);
    $this->entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $this->notification = $this->proprietaire->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Notification du propriétaire', 'url' => '/'],
    ]);
});

it('refuse à un autre membre chaque ressource du propriétaire', function (string $methode, Closure $url, array $donnees = []) {
    $reponse = $this->actingAs($this->intrus)->call($methode, $url->call($this), $donnees);

    expect($reponse->status())->toBeIn([403, 404]);
})->with([
    'modifier une publication (formulaire)' => ['GET', fn () => route('espace.publications.edit', $this->publication)],
    'modifier une publication' => ['PUT', fn () => route('espace.publications.update', $this->publication), ['titre' => 'Piratée par un intrus']],
    'clôturer une publication' => ['POST', fn () => route('espace.publications.cloturer', $this->publication), ['motif' => 'Poste pourvu']],
    'prolonger une publication' => ['POST', fn () => route('espace.publications.prolonger', $this->publication)],
    'dupliquer une publication' => ['POST', fn () => route('espace.publications.dupliquer', $this->publication)],
    'supprimer une publication' => ['DELETE', fn () => route('espace.publications.destroy', $this->publication)],
    'remonter une publication' => ['POST', fn () => route('espace.publications.remonter', $this->publication)],
    'confirmation de publication' => ['GET', fn () => route('publier.confirmation', $this->publication)],
    'voir les réponses reçues' => ['GET', fn () => route('espace.reponses.index', $this->publication)],
    'changer le statut d’une réponse' => ['PATCH', fn () => route('espace.reponses.update', $this->reponse), ['statut' => StatutReponse::Retenue->value]],
    'modifier une alerte (formulaire)' => ['GET', fn () => route('alertes.edit', $this->alerte)],
    'modifier une alerte' => ['PUT', fn () => route('alertes.update', $this->alerte), ['nom' => 'Piratée', 'frequence' => 'instantanee']],
    'mettre en pause une alerte' => ['POST', fn () => route('alertes.basculer', $this->alerte)],
    'supprimer une alerte' => ['DELETE', fn () => route('alertes.destroy', $this->alerte)],
    'ouvrir une notification' => ['GET', fn () => route('espace.notifications.ouvrir', $this->notification->id)],
    'supprimer un commentaire' => ['DELETE', fn () => route('commentaires.destroy', $this->commentaire)],
    'voir un reçu' => ['GET', fn () => route('espace.recu', $this->commande)],
    'télécharger un reçu' => ['GET', fn () => route('espace.recu.pdf', $this->commande)],
    'suivre un paiement' => ['GET', fn () => route('paiements.retour', $this->commande)],
    'simuler un paiement' => ['POST', fn () => route('paiements.simuler', $this->commande), ['resultat' => 'succes']],
]);

it('laisse intactes les ressources du propriétaire après les tentatives', function () {
    $this->actingAs($this->intrus);
    $this->put(route('espace.publications.update', $this->publication), ['titre' => 'Piratée par un intrus']);
    $this->delete(route('espace.publications.destroy', $this->publication));
    $this->put(route('alertes.update', $this->alerte), ['nom' => 'Piratée', 'frequence' => 'instantanee']);
    $this->delete(route('alertes.destroy', $this->alerte));
    $this->delete(route('commentaires.destroy', $this->commentaire));
    $this->patch(route('espace.reponses.update', $this->reponse), ['statut' => StatutReponse::Retenue->value]);

    expect($this->publication->refresh()->titre)->toBe('Offre du propriétaire')
        ->and($this->publication->trashed())->toBeFalse()
        ->and($this->alerte->refresh()->nom)->toBe('Alerte du propriétaire')
        ->and(Commentaire::query()->whereKey($this->commentaire->id)->exists())->toBeTrue()
        ->and($this->reponse->refresh()->statut)->toBe(StatutReponse::Envoyee)
        ->and($this->notification->refresh()->read_at)->toBeNull();
});

it('refuse d’acheter ou d’essayer au nom de l’entreprise d’un autre membre', function () {
    $this->actingAs($this->intrus);

    $this->post(route('commandes.store'), ['article_type' => 'plan', 'code' => 'entreprise_30j', 'entreprise_id' => $this->entreprise->id])
        ->assertSessionHasErrors('entreprise_id');
    $this->post(route('essais.store'), ['plan' => 'entreprise_30j', 'entreprise_id' => $this->entreprise->id])
        ->assertSessionHasErrors('entreprise_id');

    expect(Commande::query()->where('utilisateur_id', $this->intrus->id)->exists())->toBeFalse();
});

it('refuse de publier au nom de l’entreprise d’un autre membre', function () {
    $this->actingAs($this->intrus)->post(route('publier.store', 'emploi'), [
        'entreprise_id' => $this->entreprise->id,
        'titre' => 'Offre au nom d’une autre entreprise',
        'contenu' => str_repeat('Description détaillée du poste proposé. ', 4),
    ])->assertSessionHasErrors('entreprise_id');

    expect(Publication::query()->where('auteur_id', $this->intrus->id)->exists())->toBeFalse();
});

it('n’affiche jamais les données d’un autre membre dans « Mon espace »', function (string $route) {
    $this->actingAs($this->intrus)->get(route($route))
        ->assertOk()
        ->assertDontSee('Alerte du propriétaire')
        ->assertDontSee('Notification du propriétaire')
        ->assertDontSee('REC-2026-000001');
})->with(['espace.index', 'alertes.index', 'espace.notifications', 'espace.publications.index', 'espace.abonnement']);
