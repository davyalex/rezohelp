<?php

use App\Enums\EmplacementPublicite;
use App\Models\Administrateur;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Publicite;
use App\Services\Publicites;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    referentielsPublication();
    $this->admin = Administrateur::factory()->administrateur()->create();
    $this->moderateur = Administrateur::factory()->moderateur()->create();
});

function donneesPublicite(array $autres = []): array
{
    return [
        'titre' => 'Campagne rentrée',
        'annonceur' => 'Banque Atlantique',
        'lien' => 'https://banque.ci/rentree',
        'texte_alternatif' => 'Banque Atlantique : ouvrez un compte',
        'emplacement' => EmplacementPublicite::FilEntreCartes->value,
        'debut_le' => '',
        'fin_le' => '',
        'active' => '1',
        'poids' => '2',
        'image_principale' => UploadedFile::fake()->image('banniere.png', 1280, 320),
        ...$autres,
    ];
}

it('réserve les publicités à la permission publicites.gerer', function () {
    $this->actingAs($this->moderateur, 'admin')->get(route('admin.publicites.index'))->assertForbidden();
    $this->actingAs($this->admin, 'admin')->get(route('admin.publicites.index'))->assertOk()
        ->assertSee('Bannière dans le fil')
        ->assertSee('Marge gauche (grand écran)');
});

it('crée une publicité avec ses images, journalisée, aussitôt diffusée', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.publicites.store'), donneesPublicite(['image_secondaire' => UploadedFile::fake()->image('mobile.png', 640, 200)]))
        ->assertRedirect();

    $publicite = Publicite::query()->sole();
    expect($publicite)
        ->emplacement->toBe(EmplacementPublicite::FilEntreCartes)
        ->poids->toBe(2)
        ->and($publicite->urlImage(Publicite::IMAGE_PRINCIPALE))->toEndWith('.webp')
        ->and($publicite->urlImage(Publicite::IMAGE_SECONDAIRE))->not->toBeNull()
        ->and(JournalActivite::query()->where('action', 'publicite.creee')->sole()->administrateur_id)->toBe($this->admin->id)
        ->and(app(Publicites::class)->annonce(EmplacementPublicite::FilEntreCartes))->id->toBe($publicite->id);
});

it('refuse une image trop petite ou aux mauvaises proportions, un lien non https, une période inversée', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.publicites.store'), donneesPublicite([
        'image_principale' => UploadedFile::fake()->image('carre.png', 640, 640),
        'lien' => 'http://banque.ci',
        'debut_le' => '2026-12-01',
        'fin_le' => '2026-11-01',
    ]))->assertSessionHasErrors(['image_principale', 'lien', 'fin_le']);

    $this->post(route('admin.publicites.store'), donneesPublicite([
        'emplacement' => EmplacementPublicite::ColonneFil->value,
        'image_principale' => UploadedFile::fake()->image('carre.png', 500, 500),
        'image_secondaire' => UploadedFile::fake()->image('autre.png', 500, 500),
    ]))->assertSessionHasErrors(['image_secondaire' => 'Cet emplacement n’utilise pas d’image secondaire.']);

    expect(Publicite::query()->count())->toBe(0);
});

it('modifie, désactive puis supprime une publicité, qui disparaît du site', function () {
    $publicite = Publicite::factory()->emplacement(EmplacementPublicite::FilEntreCartes)->avecImage()->create();
    $this->actingAs($this->admin, 'admin');

    $this->put(route('admin.publicites.update', $publicite), donneesPublicite(['titre' => 'Nouveau nom', 'image_principale' => null]))
        ->assertRedirect(route('admin.publicites.edit', $publicite));
    expect($publicite->refresh()->titre)->toBe('Nouveau nom');

    // Changer d'emplacement change le format : une nouvelle image est exigée.
    $this->put(route('admin.publicites.update', $publicite), donneesPublicite(['emplacement' => 'marge_gauche', 'image_principale' => null]))
        ->assertSessionHasErrors('image_principale');

    $this->post(route('admin.publicites.basculer', $publicite))->assertSessionHas('statut');
    expect($publicite->refresh()->active)->toBeFalse()
        ->and(app(Publicites::class)->annonce(EmplacementPublicite::FilEntreCartes))->toBeNull();

    $this->delete(route('admin.publicites.destroy', $publicite))->assertRedirect(route('admin.publicites.index'));
    expect(Publicite::query()->count())->toBe(0)
        ->and(Publicite::withTrashed()->count())->toBe(1)
        ->and(JournalActivite::query()->pluck('action')->all())->toContain('publicite.modifiee', 'publicite.desactivee', 'publicite.supprimee');
});

it('sert la liste en DataTables avec statistiques et menu d’actions', function () {
    $publicite = Publicite::factory()->avecImage()->create();
    $publicite->forceFill(['nb_impressions' => 2000, 'nb_clics' => 30])->save();

    $ligne = $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.publicites.index', ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->json('data.0');

    expect($ligne['nb_impressions'])->toBe('2 000')
        ->and($ligne['taux'])->toBe('1,50 %')
        ->and($ligne['etat'])->toContain('En cours')
        ->and($ligne['actions'])->toContain('popovertarget="actions-publicite-'.$publicite->id.'"');
});

it('règle depuis la page Publicités l’affichage sur le site et le lien « Annoncez ici »', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.publicites.reglages'), ['publicites_actives' => '0', 'publicite_contact' => 'mailto:pub@rezohelp.ci'])
        ->assertSessionHas('statut');

    expect(Parametre::booleen('publicites_actives'))->toBeFalse()
        ->and(Parametre::texte('publicite_contact'))->toBe('mailto:pub@rezohelp.ci')
        ->and(JournalActivite::query()->where('action', 'parametre.modifie')->count())->toBe(2);

    $this->get(route('admin.publicites.index'))->assertSee('Publicités coupées sur le site');

    $this->put(route('admin.publicites.reglages'), ['publicites_actives' => '1', 'publicite_contact' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('publicite_contact');
    $this->actingAs($this->moderateur, 'admin')->put(route('admin.publicites.reglages'), ['publicites_actives' => '1'])->assertForbidden();
});
