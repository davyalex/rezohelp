<?php

use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypeReponse;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Queue;

/**
 * D-072 : le numéro de l'auteur d'une demande de prestation est visible par tous (visiteurs compris)
 * quand il choisit de l'afficher ; sinon, aucun numéro n'apparaît.
 */
beforeEach(function () {
    referentielsPublication();
    $this->auteur = Utilisateur::factory()->create(['telephone' => '+2250708091011']);
    $this->prestataire = Utilisateur::factory()->create();
});

function demandeAvecContact(Utilisateur $auteur, bool $partage = true): Publication
{
    $demande = Publication::factory()->publiee()->create([
        'type' => 'service', 'sous_type' => SousTypeService::Demande, 'auteur_id' => $auteur->id, 'titre' => 'Cherche plombier à Yopougon',
    ]);
    $demande->detailService()->create([
        'metier' => 'Plomberie',
        'afficher_telephone' => $partage,
        'telephone_contact' => $partage ? '+2250506070809' : null,
        'whatsapp' => $partage ? '+2250506070809' : null,
    ]);

    return $demande;
}

it('enregistre le numéro de l’auteur d’une demande quand il accepte de le donner aux prestataires', function () {
    Queue::fake();

    $this->actingAs($this->auteur)->post('/publier/service-demande', donneesServiceDemande([
        'telephone_contact' => '05 06 07 08 09', 'afficher_telephone' => '1',
    ]))->assertSessionHasNoErrors();

    $detail = Publication::query()->sole()->detailService;
    expect($detail?->telephone_contact)->toBe('+2250506070809')
        ->and($detail?->afficher_telephone)->toBeTrue()
        ->and($detail?->whatsapp)->toBe('+2250506070809');
});

it('n’enregistre aucun numéro quand l’auteur refuse de le donner', function () {
    Queue::fake();

    $this->actingAs($this->auteur)->post('/publier/service-demande', donneesServiceDemande([
        'telephone_contact' => '05 06 07 08 09', 'afficher_telephone' => '0',
    ]))->assertSessionHasNoErrors();

    expect(Publication::query()->sole()->detailService?->telephone_contact)->toBeNull();
});

it('exige un numéro valide quand l’auteur choisit de le donner', function () {
    $this->actingAs($this->auteur)->post('/publier/service-demande', donneesServiceDemande([
        'telephone_contact' => '', 'afficher_telephone' => '1',
    ]))->assertSessionHasErrors('telephone_contact');
});

it('montre le numéro de l’auteur à tout le monde, visiteur compris, quand il l’a choisi', function () {
    $demande = demandeAvecContact($this->auteur);

    $this->get(route('publications.show', $demande->slug))
        ->assertOk()
        ->assertSee('Appelez l’auteur de la demande')
        ->assertSee('href="tel:+2250506070809"', false)
        ->assertSee('https://wa.me/2250506070809', false);

    // Un prestataire connecté voit aussi le numéro, et peut répondre « Je suis disponible ».
    $this->actingAs($this->prestataire)->get(route('publications.show', $demande->slug))
        ->assertSee('href="tel:+2250506070809"', false)
        ->assertSee('Je suis disponible');
});

it('ne montre aucun numéro quand l’auteur a choisi de ne pas l’afficher', function () {
    $demande = demandeAvecContact($this->auteur, partage: false);

    $this->get(route('publications.show', $demande->slug))->assertOk()->assertDontSee('tel:', false);

    Reponse::factory()->create(['publication_id' => $demande->id, 'utilisateur_id' => $this->prestataire->id, 'type' => TypeReponse::Proposition]);
    $this->actingAs($this->prestataire)->get(route('publications.show', $demande->slug))
        ->assertSee('L’auteur a préféré ne pas afficher son numéro')
        ->assertDontSee('tel:', false);
});

it('ne montre plus le numéro quand la demande n’est plus en ligne', function () {
    $demande = demandeAvecContact($this->auteur);
    $demande->forceFill(['statut' => StatutPublication::Cloturee])->save();

    $this->get(route('publications.show', $demande->slug))->assertDontSee('tel:+2250506070809', false);
});
