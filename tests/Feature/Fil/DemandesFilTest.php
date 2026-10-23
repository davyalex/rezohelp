<?php

use App\Enums\SousTypeService;
use App\Models\Publication;

/**
 * D-069 : les demandes de prestation (ceux qui cherchent un prestataire) ont leur onglet
 * et sont mises en avant dans le fil.
 */
beforeEach(fn () => referentielsPublication());

function demande(array $attributs = []): Publication
{
    return Publication::factory()->publiee()->create(['type' => 'service', 'sous_type' => SousTypeService::Demande, ...$attributs]);
}

it('liste seulement les demandes de prestation sur /demandes, avec l’onglet actif', function () {
    demande(['titre' => 'Cherche plombier pour une fuite à Yopougon']);
    Publication::factory()->serviceOffre()->publiee()->create(['titre' => 'Électricien disponible à Cocody']);
    Publication::factory()->emploi()->publiee()->create(['titre' => 'Comptable confirmé']);

    $this->get('/demandes')
        ->assertOk()
        ->assertSee('Demandes de prestation à Abidjan')
        ->assertSee('Cherche plombier pour une fuite à Yopougon')
        ->assertDontSee('Électricien disponible à Cocody')
        ->assertDontSee('Comptable confirmé')
        ->assertSee('Publier mon besoin')
        ->assertSee('data-suite="'.e(route('fil.suite', ['sous_type' => 'demande', 'type' => 'service'])).'"', false);

    // Onglet « Demandes » actif.
    expect(preg_match('#href="'.preg_quote(route('fil.demandes'), '#').'"\s+aria-current="page"#', (string) $this->get('/demandes')->getContent()))->toBe(1);
});

it('garde le filtre des demandes dans la suite du fil', function () {
    demande(['titre' => 'Cherche nounou à Angré']);
    Publication::factory()->serviceOffre()->publiee()->create(['titre' => 'Nounou disponible à Angré']);

    $this->get('/fil/suite?type=service&sous_type=demande')
        ->assertOk()
        ->assertSee('Cherche nounou à Angré')
        ->assertDontSee('Nounou disponible à Angré');
});

it('met en avant les dernières demandes sur le fil général, avec le bouton « Je suis disponible »', function () {
    demande(['titre' => 'Cherche chauffeur pour la semaine']);
    Publication::factory()->count(3)->emploi()->publiee()->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Ils cherchent un prestataire')
        ->assertSee('Voir toutes les demandes')
        ->assertSee('Demande de prestation')
        ->assertSee('Je suis disponible');
});

it('n’affiche pas le bloc des demandes quand il n’y en a aucune', function () {
    Publication::factory()->count(3)->emploi()->publiee()->create();

    $this->get('/')->assertDontSee('Ils cherchent un prestataire');
});

it('montre les demandes dans les colonnes latérales sur tous les onglets, sauf celui des demandes', function () {
    demande(['titre' => 'Cherche couturière à Abobo']);
    Publication::factory()->count(3)->emploi()->publiee()->create();

    // Colonne de droite (bloc) et colonne de gauche (lien avec le nombre de demandes en cours).
    $this->get('/emplois')
        ->assertSee('Ils cherchent un prestataire')
        ->assertSee('Cherche couturière à Abobo')
        ->assertSee('Demandes de prestation');
    $this->get('/formations')->assertSee('Ils cherchent un prestataire');
    $this->get('/demandes')->assertDontSee('Ils cherchent un prestataire');
});

it('invite à publier son besoin quand aucune demande n’est en ligne', function () {
    $this->get('/demandes')
        ->assertOk()
        ->assertSee('Aucune demande de prestation pour le moment.')
        ->assertSee(route('publier.formulaire', 'service-demande'), false);
});
