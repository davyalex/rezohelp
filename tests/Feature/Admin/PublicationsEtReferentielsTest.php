<?php

use App\Enums\StatutPublication;
use App\Models\Administrateur;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\MiseEnAvant;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\Publication;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use App\Notifications\PublicationExpireBientot;
use App\Notifications\PublicationRemiseEnAttente;
use App\Services\CacheFil;
use App\Services\Referentiels;
use Database\Seeders\SourcesVeilleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->admin = Administrateur::factory()->administrateur()->create();
    $this->auteur = Utilisateur::factory()->create();
});

it('filtre la liste des publications côté serveur', function () {
    $cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $cible = Publication::factory()->formation()->publiee()->create(['commune_id' => $cocody->id, 'auteur_id' => $this->auteur->id]);
    Publication::factory()->emploi()->publiee()->create(['commune_id' => $cocody->id]);
    Publication::factory()->formation()->enAttente()->create(['commune_id' => $cocody->id]);

    $reponse = $this->actingAs($this->admin, 'admin')->getJson(route('admin.publications.index', [
        'draw' => 1, 'start' => 0, 'length' => 25, 'type' => 'formation', 'statut' => 'publiee', 'commune_id' => $cocody->id, 'auteur_id' => $this->auteur->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

    expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$cible->id])
        ->and($reponse->json('recordsTotal'))->toBe(1);
});

it('modifie une publication en journalisant l’avant / après, sans toucher au statut', function () {
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id]);
    $categorie = Categorie::query()->where('type', 'emploi')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->put(route('admin.publications.update', $publication), [
        'titre' => 'Comptable confirmé pour PME', 'contenu' => contenuValide('Poste de comptable confirmé, maîtrise du SYSCOHADA exigée.'),
        'categorie_id' => $categorie->id, 'commune_id' => null, 'expire_le' => now()->addDays(10)->toDateString(),
    ])->assertRedirect(route('admin.publications.index'));

    $publication->refresh();
    expect($publication->titre)->toBe('Comptable confirmé pour PME')
        ->and($publication->extrait)->toStartWith('Poste de comptable')
        ->and($publication->statut)->toBe(StatutPublication::Publiee)
        ->and(JournalActivite::query()->where('action', 'publication.modifiee')->sole()->donnees['apres'])->toHaveKey('titre');
});

it('dépublie (retour en modération, auteur prévenu, mise en avant suspendue), prolonge et supprime', function () {
    $publication = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id, 'expire_le' => now()->addDays(2)]);
    $mise = MiseEnAvant::factory()->create(['publication_id' => $publication->id]);

    $this->actingAs($this->admin, 'admin')->post(route('admin.publications.depublier', $publication))->assertSessionHas('statut');
    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and($mise->refresh()->suspendue_le)->not->toBeNull();
    Notification::assertSentTo($this->auteur, PublicationRemiseEnAttente::class);

    $expiree = Publication::factory()->create(['statut' => StatutPublication::Expiree, 'expire_le' => now()->subDay()]);
    $this->post(route('admin.publications.prolonger', $expiree));
    expect($expiree->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and($expiree->expire_le?->toDateString())->toBe(now()->addDays(Parametre::entier('jours_prolongation'))->toDateString());

    $this->delete(route('admin.publications.destroy', $expiree));
    expect($expiree->refresh()->trashed())->toBeTrue()
        ->and(JournalActivite::query()->whereIn('action', ['publication.depubliee', 'publication.prolongee', 'publication.supprimee'])->count())->toBe(3);
});

it('expire les publications échues et prévient 3 jours avant (publications:expirer)', function () {
    $echue = Publication::factory()->publiee()->create(['expire_le' => now()->subMinute()]);
    $sansDate = Publication::factory()->publiee(now()->subDays(31))->create(['expire_le' => null]);
    $bientot = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id, 'expire_le' => now()->addDays(3)->setTime(12, 0)]);
    $plusTard = Publication::factory()->publiee()->create(['auteur_id' => $this->auteur->id, 'expire_le' => now()->addDays(10)]);
    $versionAvant = app(CacheFil::class)->versionFil();

    $this->artisan('publications:expirer')->assertSuccessful();

    expect($echue->refresh()->statut)->toBe(StatutPublication::Expiree)
        ->and($sansDate->refresh()->statut)->toBe(StatutPublication::Expiree)
        ->and($bientot->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and($plusTard->refresh()->statut)->toBe(StatutPublication::Publiee)
        ->and(app(CacheFil::class)->versionFil())->toBeGreaterThan($versionAvant);
    Notification::assertSentToTimes($this->auteur, PublicationExpireBientot::class, 1);
});

it('purge définitivement les publications supprimées depuis jours_purge_publications jours (publications:purger)', function () {
    $ancienne = Publication::factory()->create();
    $ancienne->delete();
    $ancienne->forceFill(['supprime_le' => now()->subDays(91)])->saveQuietly();
    $recente = Publication::factory()->create();
    $recente->delete();

    $this->artisan('publications:purger')->assertSuccessful();

    expect(Publication::withTrashed()->whereKey($ancienne->id)->exists())->toBeFalse()
        ->and(Publication::withTrashed()->whereKey($recente->id)->exists())->toBeTrue();
});

it('crée une catégorie, l’ajoute au cache des référentiels et la déplace avec les flèches', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), ['type' => 'service', 'nom' => 'Entretien de piscines', 'icone' => '❄️', 'actif' => '1'])
        ->assertRedirect(route('admin.categories.index'));

    $nouvelle = Categorie::query()->where('nom', 'Entretien de piscines')->firstOrFail();
    expect($nouvelle->slug)->toBe('service-entretien-de-piscines')
        ->and(app(Referentiels::class)->nomCategorie($nouvelle->id))->toBe('Entretien de piscines')
        ->and(JournalActivite::query()->where('action', 'categorie.cree')->exists())->toBeTrue();

    $precedente = Categorie::query()->where('type', 'service')->whereNull('parent_id')->whereKeyNot($nouvelle->id)->orderByDesc('ordre')->orderByDesc('id')->firstOrFail();
    $this->post(route('admin.categories.deplacer', $nouvelle), ['sens' => 'haut']);

    expect($nouvelle->refresh()->ordre)->toBeLessThan($precedente->refresh()->ordre);
});

it('refuse une sous-catégorie rattachée à une catégorie d’un autre type', function () {
    $parentEmploi = Categorie::query()->where('type', 'emploi')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), ['type' => 'service', 'nom' => 'Mauvais rattachement', 'parent_id' => $parentEmploi->id])
        ->assertSessionHasErrors('parent_id');
});

it('désactive une commune et un pays : ils disparaissent des listes publiques', function () {
    $cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $senegal = Pays::query()->where('code', 'SN')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->put(route('admin.communes.update', $cocody), ['pays_code' => 'CI', 'ville' => 'Abidjan', 'nom' => 'Cocody', 'actif' => '0'])->assertRedirect();
    $this->put(route('admin.pays.update', $senegal), ['code' => 'sn', 'nom' => 'Sénégal', 'indicatif' => '+221', 'actif' => '0'])->assertRedirect();

    expect(app(Referentiels::class)->communeParSlug('cocody'))->toBeNull()
        ->and($senegal->refresh()->actif)->toBeFalse()
        ->and(JournalActivite::query()->whereIn('action', ['commune.modifie', 'pays.modifie'])->count())->toBe(2);
    $this->get(route('register'))->assertDontSee('+221');
});

it('enregistre les paramètres typés, avec leur auteur et une ligne de journal par changement', function () {
    $this->actingAs($this->admin, 'admin')->put(route('admin.parametres.update'), [
        'parametres' => [
            ...Parametre::query()->pluck('valeur', 'cle')->all(),
            'quotas_actifs' => '1',
            'frequence_sponsorise' => '6',
            'mots_a_risque' => '["frais de dossier", "avance"]',
        ],
    ])->assertSessionHas('statut', '3 paramètre(s) modifié(s).');

    expect(Parametre::booleen('quotas_actifs'))->toBeTrue()
        ->and(Parametre::entier('frequence_sponsorise'))->toBe(6)
        ->and(Parametre::liste('mots_a_risque'))->toBe(['frais de dossier', 'avance'])
        ->and(Parametre::query()->findOrFail('quotas_actifs')->modifie_par)->toBe($this->admin->id)
        ->and(JournalActivite::query()->where('action', 'parametre.modifie')->count())->toBe(3);
});

it('refuse un JSON invalide ou un entier négatif', function () {
    $this->actingAs($this->admin, 'admin')->put(route('admin.parametres.update'), [
        'parametres' => [...Parametre::query()->pluck('valeur', 'cle')->all(), 'mots_a_risque' => '[non', 'jours_grace' => '-2'],
    ])->assertSessionHasErrors(['parametres.mots_a_risque', 'parametres.jours_grace']);
});

it('vide le cache du fil et des référentiels depuis les boutons', function () {
    $version = app(CacheFil::class)->versionFil();

    $this->actingAs($this->admin, 'admin')->post(route('admin.cache.vider-fil'))->assertSessionHas('statut');
    $this->post(route('admin.cache.vider-referentiels'))->assertSessionHas('statut');

    expect(app(CacheFil::class)->versionFil())->toBe($version + 1)
        ->and(JournalActivite::query()->whereIn('action', ['cache.fil_vide', 'cache.referentiels_vides'])->count())->toBe(2);
});

it('ajoute une source de veille en normalisant son domaine', function () {
    $this->seed(SourcesVeilleSeeder::class);

    $this->actingAs($this->admin, 'admin')->post(route('admin.veille.sources.store'), [
        'nom' => 'Novojob Test', 'domaine' => 'https://www.Novojob-Test.ci/offres', 'niveau_confiance' => 'moyen', 'publication_auto' => '0', 'actif' => '1',
    ])->assertRedirect(route('admin.veille.sources.index'));

    expect(SourceVeille::query()->where('nom', 'Novojob Test')->value('domaine'))->toBe('novojob-test.ci')
        ->and(JournalActivite::query()->where('action', 'source_veille.cree')->exists())->toBeTrue();
});

it('affiche le tableau de bord avec ses compteurs et le bandeau d’import en retard', function () {
    Publication::factory()->enAttente()->count(2)->create();

    $this->actingAs($this->admin, 'admin')->get(route('admin.tableau-de-bord'))
        ->assertOk()
        ->assertSee('En attente de modération')
        ->assertSee('Aucun import de la veille réussi depuis plus de 9 heures.')
        ->assertSee('Modérer (2)');
});
