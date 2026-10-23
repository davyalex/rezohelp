<?php

use App\Enums\StatutPublication;
use App\Models\Administrateur;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Notification;

/**
 * Lot 10, parcours 3 : publication d'un artisan → modération → fil (dans un vrai navigateur).
 */
beforeEach(function () {
    referentielsPublication();
    Notification::fake();
});

it('publie une annonce d’artisan, la fait valider par un modérateur, puis la retrouve dans le fil', function () {
    $artisan = Utilisateur::factory()->create();
    Administrateur::factory()->moderateur()->create(['nom_utilisateur' => 'moderatrice']);
    $cocody = (string) Commune::query()->where('slug', 'cocody')->value('id');

    $this->actingAs($artisan);
    $page = visit('/publier/service-offre');

    $page->select('categorie_id', (string) Categorie::query()->where('slug', 'service-electricite')->value('id'))
        ->select('commune_id', $cocody)
        ->fill('titre', 'Électricien disponible à Cocody')
        ->fill('textarea[name="contenu"]', 'Dépannage, installation et mise aux normes, 7 jours sur 7.')
        ->check('zones_intervention[]', $cocody)
        ->fill('tarif_indicatif', '5000')
        ->fill('telephone_contact', '05 06 07 08 09');

    // Le serveur de test du plugin ne lit pas encore les envois multipart (« @TODO files » dans
    // LaravelHttpServer) : sans photo, le formulaire part en urlencoded. Les photos sont couvertes
    // par les tests Feature (PublierTest, EnvoisFichiersTest).
    $page->script("(() => { const f = document.querySelector('form[enctype=\"multipart/form-data\"]'); f.enctype = 'application/x-www-form-urlencoded'; f.querySelectorAll('input[type=file]').forEach((c) => { c.disabled = true; }); return true; })()");

    $page->press('Publier')
        ->assertSee('sera visible après vérification');

    // Première publication du compte : toujours en modération (CONTEXTE §11.6), absente du fil.
    $publication = Publication::query()->sole();
    expect($publication->statut)->toBe(StatutPublication::EnAttente);
    $page->navigate('/')->assertDontSee('Électricien disponible à Cocody');

    $page->navigate('/admin/connexion')
        ->fill('nom_utilisateur', 'moderatrice')
        ->fill('mot_de_passe', 'Admin123')
        ->press('Se connecter')
        ->assertPathIs('/admin')
        ->navigate('/admin/moderation/'.$publication->id)
        ->assertSee('Électricien disponible à Cocody')
        ->click('form[action$="/valider"] button')
        ->assertNoJavaScriptErrors();

    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee);
    $page->navigate('/')->assertSee('Électricien disponible à Cocody');
});
