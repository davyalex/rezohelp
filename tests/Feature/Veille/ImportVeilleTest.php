<?php

use App\Actions\Admin\GererImportVeille;
use App\Contracts\ClientIA;
use App\Contracts\SourceVeille;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use App\Enums\SourcePublication;
use App\Enums\StatutAppelIa;
use App\Enums\StatutImportVeille;
use App\Enums\StatutLigneVeille;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Events\PublicationPubliee;
use App\Exceptions\ReponseIAInvalideException;
use App\Jobs\EnrichirPublicationIA;
use App\Jobs\ImporterFichierVeille;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\ImportVeille;
use App\Models\LigneImportVeille;
use App\Models\Publication;
use App\Models\SourceVeille as Source;
use App\Models\Utilisateur;
use App\Services\Veille\FakeSourceVeille;
use Database\Seeders\SourcesVeilleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

const ENTETE_VEILLE = 'Catégorie,Intitulé,Entreprise/Organisme,Lieu,Contrat/Durée/Prix,Date publication,Domaine,Source,Lien,Résumé';

beforeEach(function () {
    referentielsPublication();
    $this->seed(SourcesVeilleSeeder::class);
    $this->dossier = storage_path('framework/testing/veille-'.uniqid());
    File::ensureDirectoryExists($this->dossier);
    app()->instance(SourceVeille::class, new FakeSourceVeille($this->dossier));
});

afterEach(fn () => File::deleteDirectory($this->dossier));

/**
 * @param  list<list<string>>  $lignes  colonnes dans l'ordre de ENTETE_VEILLE
 */
function deposerVeille(string $dossier, string $nom, array $lignes): void
{
    $flux = fopen('php://temp', 'r+');
    fwrite($flux, ENTETE_VEILLE."\n");
    foreach ($lignes as $ligne) {
        fputcsv($flux, $ligne, ',', '"', '');
    }
    rewind($flux);
    file_put_contents($dossier.DIRECTORY_SEPARATOR.$nom, stream_get_contents($flux));
    fclose($flux);
}

function ligneEmploi(string $lien = 'https://www.emploi.ci/offre/comptable-12345?utm_source=veille', array $surcharge = []): array
{
    return array_values(array_replace([
        "Offre d'emploi", 'Comptable confirmé', 'Société Ivoirienne de Distribution', 'Abidjan, Plateau', 'CDI', '2026-10-15',
        'Comptabilité', 'Emploi.ci', $lien, 'Poste de comptable confirmé au Plateau, 5 ans d’expérience, maîtrise de SAGE.',
    ], $surcharge));
}

it('importe le fichier de la veille : 3 types, commune, source, détails, journal des lignes', function () {
    copy(base_path('tests/Fixtures/veille/veille-2026-10-16-0051.csv'), $this->dossier.'/veille-2026-10-16-0051.csv');

    $this->artisan('veille:importer')->assertSuccessful()->expectsOutputToContain('1 fichier(s) mis en file');

    $import = ImportVeille::query()->sole();
    expect($import->statut)->toBe(StatutImportVeille::Termine)
        ->and([$import->nb_lignes, $import->nb_importees, $import->nb_doublons, $import->nb_rejetees])->toBe([3, 3, 0, 0])
        ->and($import->lignes()->where('statut', StatutLigneVeille::Importee->value)->count())->toBe(3);

    $emploi = Publication::query()->where('type', TypePublication::Emploi->value)->sole();
    expect($emploi->source)->toBe(SourcePublication::VeilleIa)
        ->and($emploi->commune_id)->toBe(Commune::query()->where('slug', 'plateau')->value('id'))
        ->and($emploi->source_nom)->toBe('Emploi.ci')
        ->and($emploi->source_url)->toContain('emploi.ci/offre/comptable-confirme-12345')
        ->and($emploi->auteur_nom_affiche)->toBe('Société Ivoirienne de Distribution')
        ->and($emploi->detailEmploi?->type_contrat)->toBe(TypeContrat::Cdi)
        ->and($emploi->detailEmploi?->contact_candidature)->toBe($emploi->source_url)
        ->and($emploi->type_contrat)->toBe(TypeContrat::Cdi);

    $service = Publication::query()->where('type', TypePublication::Service->value)->sole();
    expect($service->sous_type?->value)->toBe('offre')
        ->and($service->commune_id)->toBe(Commune::query()->where('slug', 'cocody')->value('id'))
        ->and($service->categorie_id)->toBe(Categorie::query()->where('type', 'service')->where('nom', 'Électricité')->value('id'))
        ->and($service->detailService?->tarif_indicatif)->toBe(5000)
        ->and($service->detailService?->afficher_telephone)->toBeFalse();

    $formation = Publication::query()->where('type', TypePublication::Formation->value)->sole();
    expect($formation->detailFormation?->prix)->toBe(0)
        ->and($formation->detailFormation?->duree)->toBe('3 mois')
        ->and($formation->source_veille_id)->toBe(Source::query()->where('domaine', 'orangedigitalcenters.com')->value('id'));
});

it('met en ligne automatiquement seulement une source de confiance haute avec un score suffisant', function () {
    Event::fake([PublicationPubliee::class]);
    copy(base_path('tests/Fixtures/veille/veille-2026-10-16-0051.csv'), $this->dossier.'/veille.csv');

    $this->artisan('veille:importer');

    // FakeClientIA note 85 une annonce sans signal ; Emploi.ci et Orange Digital Center sont de confiance haute,
    // Facebook de confiance basse.
    expect(Publication::query()->where('statut', StatutPublication::Publiee->value)->pluck('source_nom')->sort()->values()->all())
        ->toBe(['Emploi.ci', 'Orange Digital Center'])
        ->and(Publication::query()->where('source_nom', 'Facebook')->value('statut'))->toBe(StatutPublication::EnAttente)
        ->and(Publication::query()->whereNotNull('publiee_le')->whereColumn('tri_le', 'publiee_le')->count())->toBe(2);
    Event::assertDispatchedTimes(PublicationPubliee::class, 2);
});

it('laisse en attente quand le score est sous le seuil ou que la source ne publie pas seule', function () {
    parametre('seuil_publication_auto', '90');
    Source::query()->where('domaine', 'orangedigitalcenters.com')->update(['publication_auto' => false]);
    copy(base_path('tests/Fixtures/veille/veille-2026-10-16-0051.csv'), $this->dossier.'/veille.csv');

    $this->artisan('veille:importer');

    expect(Publication::query()->where('statut', StatutPublication::Publiee->value)->count())->toBe(0)
        ->and(Publication::query()->pluck('score_fiabilite')->unique()->all())->toBe([85]);
});

it('ignore un fichier déjà importé', function () {
    deposerVeille($this->dossier, 'veille-1.csv', [ligneEmploi()]);

    $this->artisan('veille:importer');
    $this->artisan('veille:importer')->expectsOutputToContain('Aucun nouveau fichier.');

    expect(ImportVeille::query()->count())->toBe(1)->and(Publication::query()->count())->toBe(1);
});

it('écarte un doublon d’URL (suivi, slash final, www, http) importé depuis un autre fichier', function () {
    deposerVeille($this->dossier, 'veille-1.csv', [ligneEmploi()]);
    $this->artisan('veille:importer');
    $originale = Publication::query()->sole();

    deposerVeille($this->dossier, 'veille-2.csv', [
        ligneEmploi('http://emploi.ci/offre/comptable-12345/?fbclid=xyz'),
        ligneEmploi('https://www.emploi.ci/offre/autre-poste-999', [1 => 'Assistant comptable']),
    ]);
    $this->artisan('veille:importer');

    $second = ImportVeille::query()->where('nom_fichier', 'veille-2.csv')->sole();
    expect([$second->nb_importees, $second->nb_doublons])->toBe([1, 1])
        ->and(LigneImportVeille::query()->where('statut', 'doublon')->sole()->publication_id)->toBe($originale->id)
        ->and(Publication::query()->count())->toBe(2);
});

it('rejette les lignes incomplètes, au lien invalide, de catégorie inconnue ou de source désactivée', function () {
    Source::query()->where('domaine', 'novojob.com')->update(['actif' => false]);
    deposerVeille($this->dossier, 'veille.csv', [
        ligneEmploi('', []),
        ligneEmploi('javascript:alert(1)'),
        ligneEmploi('https://emploi.ci/offre/1', [0 => 'Annonce immobilière']),
        ligneEmploi('https://www.novojob.com/offre/7'),
    ]);

    $this->artisan('veille:importer');

    expect(ImportVeille::query()->sole()->nb_rejetees)->toBe(4)
        ->and(LigneImportVeille::query()->orderBy('numero')->pluck('raison')->all())->toBe([
            'Colonne(s) vide(s) : lien.',
            'Lien invalide.',
            'Catégorie inconnue : « Annonce immobilière ».',
            'Source désactivée : Novojob.',
        ])
        ->and(Publication::query()->count())->toBe(0);
});

it('rattache un domaine inconnu à la source « Inconnue », de confiance basse', function () {
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi('https://jobs.exemple-inconnu.ci/offre/5', [7 => 'Site Exemple'])]);

    $this->artisan('veille:importer');

    $publication = Publication::query()->sole();
    expect($publication->source_veille_id)->toBe(Source::query()->whereNull('domaine')->value('id'))
        ->and($publication->source_nom)->toBe('Site Exemple')
        ->and($publication->statut)->toBe(StatutPublication::EnAttente);
});

it('ne garde jamais le texte source complet : résumé limité, carte réécrite', function () {
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi(surcharge: [9 => str_repeat('Texte de l’annonce d’origine très détaillé. ', 80)])]);

    $this->artisan('veille:importer');

    $publication = Publication::query()->sole();
    expect(mb_strlen($publication->contenu))->toBeLessThanOrEqual(603)
        ->and(mb_strlen($publication->extrait))->toBeLessThanOrEqual(223);
});

it('laisse l’annonce en attente quand l’IA échoue, et journalise l’échec', function () {
    app()->instance(ClientIA::class, new class implements ClientIA
    {
        public function enrichir(DemandeEnrichissement $demande): ResultatEnrichissement
        {
            throw new ReponseIAInvalideException('Réponse de l’IA hors du format attendu.');
        }
    });
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi()]);

    Queue::fake([EnrichirPublicationIA::class]);
    $this->artisan('veille:importer');
    $publication = Publication::query()->sole();

    $job = new EnrichirPublicationIA($publication->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(ReponseIAInvalideException::class);
    $job->failed(new ReponseIAInvalideException('x'));

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente)
        ->and($publication->score_fiabilite)->toBeNull()
        ->and(AppelIa::query()->sole()->statut)->toBe(StatutAppelIa::Echec)
        ->and(AppelIa::query()->sole()->import_veille_id)->toBe($publication->import_veille_id);
});

it('rejoue un import sans dupliquer ni recompter en doublons ses propres annonces', function () {
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi(), ligneEmploi('https://emploi.ci/offre/2', [1 => 'Caissier'])]);
    $this->artisan('veille:importer');
    $import = ImportVeille::query()->sole();
    $appels = AppelIa::query()->count();

    app(GererImportVeille::class)->relancer($import, Utilisateur::factory()->create());

    $import->refresh();
    expect([$import->nb_lignes, $import->nb_importees, $import->nb_doublons])->toBe([2, 2, 0])
        ->and($import->lignes()->count())->toBe(2)
        ->and(Publication::query()->count())->toBe(2)
        // Les annonces déjà notées ne repartent pas à l'IA.
        ->and(AppelIa::query()->count())->toBe($appels);
});

it('marque l’import en erreur quand le fichier est illisible', function () {
    $import = new ImportVeille(['fichier_drive_id' => 'fake-disparu', 'nom_fichier' => 'disparu.csv']);
    $import->statut = StatutImportVeille::EnCours;
    $import->save();

    $job = new ImporterFichierVeille($import->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Fichier de veille introuvable : disparu.csv'));

    expect($import->refresh()->statut)->toBe(StatutImportVeille::Erreur)
        ->and($import->erreur)->toContain('introuvable');
});

it('réévalue les annonces en attente quand leur domaine est déclaré comme source de confiance haute', function () {
    Event::fake([PublicationPubliee::class]);
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi('https://jobs.exemple-inconnu.ci/offre/5', [7 => 'Site Exemple'])]);
    $this->artisan('veille:importer');
    $publication = Publication::query()->sole();
    expect($publication->statut)->toBe(StatutPublication::EnAttente);

    $source = Source::query()->create(['nom' => 'Exemple Emploi', 'domaine' => 'exemple-inconnu.ci', 'niveau_confiance' => 'haut', 'publication_auto' => true, 'actif' => true]);

    $this->artisan('veille:reevaluer')->assertSuccessful()
        ->expectsOutputToContain('1 annonce(s) examinée(s), 1 rattachée(s) à une autre source, 1 mise(s) en ligne');

    $publication->refresh();
    expect($publication->statut)->toBe(StatutPublication::Publiee)
        ->and($publication->source_veille_id)->toBe($source->id)
        ->and($publication->source_nom)->toBe('Exemple Emploi')
        ->and($publication->publiee_le)->not->toBeNull()
        ->and($publication->tri_le?->equalTo($publication->publiee_le))->toBeTrue();
    Event::assertDispatchedTimes(PublicationPubliee::class, 1);
});

it('ne remet jamais en ligne une annonce retirée, non notée, expirée ou sous le seuil', function () {
    Event::fake([PublicationPubliee::class]);
    deposerVeille($this->dossier, 'veille.csv', [
        ligneEmploi('https://jobs.exemple-inconnu.ci/offre/1'),
        ligneEmploi('https://jobs.exemple-inconnu.ci/offre/2'),
        ligneEmploi('https://jobs.exemple-inconnu.ci/offre/3'),
        ligneEmploi('https://jobs.exemple-inconnu.ci/offre/4'),
    ]);
    $this->artisan('veille:importer');
    Source::query()->create(['nom' => 'Exemple Emploi', 'domaine' => 'exemple-inconnu.ci', 'niveau_confiance' => 'haut', 'publication_auto' => true, 'actif' => true]);

    [$retiree, $nonNotee, $expiree, $faible] = Publication::query()->orderBy('id')->get()->all();
    $retiree->forceFill(['publiee_le' => now()->subDay()])->save();
    $nonNotee->forceFill(['score_fiabilite' => null])->save();
    $expiree->forceFill(['expire_le' => now()->subMinute()])->save();
    $faible->forceFill(['score_fiabilite' => 50])->save();

    $this->artisan('veille:reevaluer')->assertSuccessful();

    expect(Publication::query()->where('statut', StatutPublication::Publiee->value)->count())->toBe(0)
        // L'annonce sous le seuil est tout de même rattachée à la nouvelle source.
        ->and($faible->refresh()->source_nom)->toBe('Exemple Emploi');
    Event::assertNotDispatched(PublicationPubliee::class);
});

it('enregistre la date de clôture, le salaire et les notes de la veille, et nettoie le texte public', function () {
    $cloture = now()->addDays(10);
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi(surcharge: [
        4 => 'CDD / CDI ; 200 000 à 300 000 F CFA',
        5 => now()->format('d/m/Y'),
        9 => 'Traitement d’appels clients dans un centre d’appels à Angré. Clôture '.$cloture->format('d/m').'. Détail non ouvert ce passage, à vérifier.',
    ])]);

    $this->artisan('veille:importer');

    $publication = Publication::query()->with('detailEmploi')->sole();
    expect($publication->contenu)->toBe('Traitement d’appels clients dans un centre d’appels à Angré.')
        ->and($publication->note_veille)->toBe('Détail non ouvert ce passage, à vérifier.')
        ->and($publication->detailEmploi?->date_limite?->toDateString())->toBe($cloture->toDateString())
        ->and($publication->expire_le?->toDateTimeString())->toBe($cloture->copy()->endOfDay()->toDateTimeString())
        ->and($publication->detailEmploi?->type_contrat)->toBe(TypeContrat::Cdd)
        ->and([$publication->detailEmploi?->salaire_min, $publication->detailEmploi?->salaire_max])->toBe([200000, 300000])
        ->and($publication->faits_cles)->toContain('Jusqu’au '.$cloture->translatedFormat('j M'));
});

it('n’affiche pas « Autre » quand le contrat n’est pas précisé', function () {
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi(surcharge: [4 => 'Contrat et salaire non précisés'])]);

    $this->artisan('veille:importer');

    expect(Publication::query()->with('detailEmploi')->sole()->detailEmploi?->type_contrat)->toBeNull();
});

it('n’importe pas une annonce déjà close', function () {
    deposerVeille($this->dossier, 'veille.csv', [ligneEmploi(surcharge: [5 => now()->subDays(5)->format('d/m/Y'), 9 => 'Vendeur. Clôture le '.now()->subDays(2)->format('d/m').'.'])]);

    $this->artisan('veille:importer');

    expect(Publication::query()->count())->toBe(0)
        ->and(LigneImportVeille::query()->sole()->raison)->toStartWith('Annonce déjà clôturée le ');
});

it('retire du fil une annonce close, puis la supprime 5 jours après sa clôture (publications:expirer)', function () {
    $close = Publication::factory()->veille()->publiee()->create(['expire_le' => now()->subHour()]);
    $closeDepuis6Jours = Publication::factory()->veille()->create(['expire_le' => now()->subDays(6)]);
    $closeDepuis6Jours->forceFill(['statut' => StatutPublication::Expiree])->save();
    $enAttenteClose = Publication::factory()->veille()->enAttente()->create(['expire_le' => now()->subHour()]);
    $membreExpire = Publication::factory()->create(['expire_le' => now()->subDays(30)]);
    $membreExpire->forceFill(['statut' => StatutPublication::Expiree])->save();

    $this->artisan('publications:expirer')->assertSuccessful()->expectsOutputToContain('1 annonce(s) de la veille supprimée(s)');

    expect($close->refresh()->statut)->toBe(StatutPublication::Expiree)
        ->and($enAttenteClose->refresh()->statut)->toBe(StatutPublication::Expiree)
        ->and(Publication::query()->whereKey($closeDepuis6Jours->id)->exists())->toBeFalse()
        ->and(Publication::withTrashed()->whereKey($closeDepuis6Jours->id)->exists())->toBeTrue()
        // Une publication de membre expirée reste : son auteur peut la prolonger.
        ->and(Publication::query()->whereKey($membreExpire->id)->exists())->toBeTrue();
});

it('expire une offre d’emploi dont la date limite de candidature est passée', function () {
    $publication = Publication::factory()->emploi()->publiee()->create(['expire_le' => now()->addDays(20)]);
    $publication->detailEmploi()->create(['type_contrat' => TypeContrat::Cdi, 'date_limite' => now()->subDay()->toDateString(), 'mode_candidature' => 'rezohelp']);

    $this->artisan('publications:expirer')->assertSuccessful();

    expect($publication->refresh()->statut)->toBe(StatutPublication::Expiree);
});

it('ne supprime jamais les annonces de la veille quand jours_suppression_veille vaut 0', function () {
    parametre('jours_suppression_veille', '0');
    $close = Publication::factory()->veille()->create(['expire_le' => now()->subDays(30)]);
    $close->forceFill(['statut' => StatutPublication::Expiree])->save();

    $this->artisan('publications:expirer')->assertSuccessful();

    expect(Publication::query()->whereKey($close->id)->exists())->toBeTrue();
});
