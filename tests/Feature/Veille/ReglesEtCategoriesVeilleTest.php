<?php

use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Jobs\EnrichirPublicationIA;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\Publication;
use App\Models\SourceVeille;
use App\Services\CorrespondanceVeille;
use App\Services\IA\ReglesClientIA;
use Database\Seeders\SourcesVeilleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    referentielsPublication();
    $this->seed(SourcesVeilleSeeder::class);
});

function regles(): ReglesClientIA
{
    return app(ReglesClientIA::class);
}

/**
 * Annonce de la veille en attente, notée ou non, rattachée à une source de confiance haute.
 */
function annonceVeille(array $attributs = []): Publication
{
    $publication = Publication::factory()->create(array_replace([
        'source' => SourcePublication::VeilleIa,
        'source_veille_id' => SourceVeille::query()->where('domaine', 'emploi.ci')->value('id'),
        'source_url' => 'https://www.emploi.ci/offre/'.fake()->unique()->numberBetween(1, 99999),
        'score_fiabilite' => null,
        'publiee_le' => null,
        'extrait' => 'Extrait de la veille',
    ], $attributs));
    $publication->forceFill(['statut' => $attributs['statut'] ?? StatutPublication::EnAttente])->save();

    return $publication;
}

it('est choisi par IA_DRIVER=regles', function () {
    config(['rezohelp.ia.driver' => 'regles']);
    app()->forgetInstance(ClientIA::class);

    expect(app(ClientIA::class))->toBeInstanceOf(ReglesClientIA::class);
});

it('note sans IA : 85 sans signal, 30 de moins par signal, sans réécrire ni deviner de catégorie', function () {
    $propre = regles()->enrichir(new DemandeEnrichissement('emploi', 'Comptable confirmé', 'Poste de comptable au Plateau, 5 ans d’expérience.', categoriesPossibles: ['Finance']));
    $douteuse = regles()->enrichir(new DemandeEnrichissement('emploi', 'Recrutement urgent', 'Frais de dossier à payer avant l’entretien.'));
    $arnaque = regles()->enrichir(new DemandeEnrichissement('emploi', 'Recrutement urgent', 'Frais de dossier à régler, appelez le 07 08 09 10 11.'));

    expect($propre->scoreFiabilite)->toBe(85)
        ->and($propre->signauxRisque)->toBe([])
        ->and($propre->texteCarte)->toBe('')
        ->and($propre->categorie)->toBeNull()
        ->and($propre->appelExterne)->toBeFalse()
        ->and($douteuse->signauxRisque)->toBe(['expression_a_risque'])
        ->and($douteuse->scoreFiabilite)->toBe(55)
        ->and($arnaque->signauxRisque)->toBe(['expression_a_risque', 'telephone_dans_texte'])
        ->and($arnaque->scoreFiabilite)->toBe(25);
});

it('suit la liste mots_a_risque modifiée dans l’admin', function () {
    parametre('mots_a_risque', json_encode(['cotisation obligatoire']));

    expect(regles()->enrichir(new DemandeEnrichissement('emploi', 'Agent commercial', 'Une cotisation obligatoire est demandée.'))->scoreFiabilite)->toBe(55);
});

it('garde l’extrait de la veille et ne journalise aucun appel payant', function () {
    config(['rezohelp.ia.driver' => 'regles']);
    app()->forgetInstance(ClientIA::class);
    $publication = annonceVeille();

    EnrichirPublicationIA::dispatchSync($publication->id);

    $publication->refresh();
    expect($publication->score_fiabilite)->toBe(85)
        ->and($publication->extrait)->toBe('Extrait de la veille')
        ->and($publication->statut)->toBe(StatutPublication::Publiee)
        ->and(AppelIa::query()->count())->toBe(0);
});

it('ne remet jamais en ligne seule une annonce de la veille retirée après sa mise en ligne', function () {
    $publication = annonceVeille(['publiee_le' => now()->subDay()]);

    EnrichirPublicationIA::dispatchSync($publication->id);

    expect($publication->refresh()->statut)->toBe(StatutPublication::EnAttente);
});

it('renote en file les annonces en ligne ou en attente, jamais celles retirées ou expirées', function () {
    Queue::fake();
    $enLigne = annonceVeille(['statut' => StatutPublication::Publiee, 'publiee_le' => now(), 'score_fiabilite' => 85]);
    $enAttente = annonceVeille(['score_fiabilite' => 85]);
    annonceVeille(['publiee_le' => now()->subDay(), 'score_fiabilite' => 85]);
    annonceVeille(['expire_le' => now()->subDay(), 'score_fiabilite' => 85]);

    $this->artisan('veille:reevaluer', ['--renoter' => true])->assertSuccessful()
        ->expectsOutputToContain('2 annonce(s) envoyée(s) à la notation');

    Queue::assertPushed(EnrichirPublicationIA::class, 2);
    Queue::assertPushed(EnrichirPublicationIA::class, fn (EnrichirPublicationIA $job): bool => in_array($job->publicationId, [$enLigne->id, $enAttente->id], true));
});

it('rattache les domaines composés de la veille à la bonne catégorie', function (string $type, string $domaine, ?string $attendue) {
    $id = app(CorrespondanceVeille::class)->categorie(TypePublication::from($type), $domaine);

    expect($id === null ? null : Categorie::query()->whereKey($id)->value('nom'))->toBe($attendue);
})->with([
    ['emploi', 'Commerce / Vente', 'Commerce et vente'],
    ['emploi', 'Finance / Comptabilité', 'Finance et comptabilité'],
    ['emploi', 'IT', 'Informatique et numérique'],
    ['emploi', 'IT / Commerce', 'Informatique et numérique'],
    ['emploi', 'Commerce / IT', 'Commerce et vente'],
    ['emploi', 'Logistique / IT', 'Transport et logistique'],
    ['emploi', 'RH / Paie', 'Administration et secrétariat'],
    ['emploi', 'Centre d\'appels', 'Commerce et vente'],
    ['emploi', 'Restauration / Hôtellerie', 'Hôtellerie et restauration'],
    ['emploi', 'Marketing digital', 'Marketing et communication'],
    ['emploi', 'Entrepreneuriat / Gestion de projet', 'Autres métiers'],
    ['service', 'Transport / Chauffeur', 'Chauffeur'],
    ['service', 'BTP / Plomberie', 'Plomberie'],
    ['service', 'Déménagement / Manutention', 'Déménagement'],
    ['service', 'Garde d\'enfants', 'Nounou'],
    ['service', 'Installation / Vidéosurveillance', 'Électronique et informatique'],
    ['service', 'Gardiennage / Sécurité', null],
    ['formation', 'Data / Bureautique', 'Informatique et numérique'],
    ['formation', 'BTP / Conduite d\'engins', 'Métiers techniques'],
]);
