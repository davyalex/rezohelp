<?php

use App\Events\PublicationPubliee;
use App\Models\Commune;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Lot 10, parcours 2 : inscription → alerte → notification (dans un vrai navigateur).
 */
beforeEach(function () {
    referentielsPublication();
    fauxSms();
});

it('crée un compte, une alerte, puis reçoit la notification d’une offre correspondante', function () {
    $page = visit('/inscription');

    $page->fill('prenoms', 'Awa')
        ->fill('nom', 'Koné')
        ->fill('telephone', '07 08 09 10 11')
        ->fill('password', 'Abidjan-Cocody-2026')
        ->fill('password_confirmation', 'Abidjan-Cocody-2026')
        ->press('Créer mon compte')
        ->assertPathIs('/verification-telephone');

    expect(Utilisateur::query()->sole()->telephone)->toBe('+2250708091011');

    $page->navigate('/mon-espace/alertes/creer')
        ->select('type', 'emploi')
        ->select('commune_id', (string) Commune::query()->where('slug', 'cocody')->value('id'))
        ->fill('nom', 'Emplois à Cocody')
        ->press('Créer l’alerte')
        ->assertPathIs('/mon-espace/alertes')
        ->assertSee('Emplois à Cocody');

    // Une offre correspondante est publiée : la correspondance tourne en file (synchrone en test).
    $offre = Publication::factory()->emploi()->publiee()->create([
        'titre' => 'Assistant comptable à Cocody',
        'commune_id' => Commune::query()->where('slug', 'cocody')->value('id'),
    ]);
    PublicationPubliee::dispatch($offre);

    $page->navigate('/mon-espace/notifications')
        ->assertSee('Alerte « Emplois à Cocody »')
        ->assertSee('Assistant comptable à Cocody')
        ->click('Assistant comptable à Cocody')
        ->assertPathIs('/publication/'.$offre->slug)
        ->assertNoJavaScriptErrors();
});
