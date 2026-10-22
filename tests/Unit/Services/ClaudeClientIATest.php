<?php

use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Exceptions\ReponseIAInvalideException;
use App\Services\IA\ClaudeClientIA;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const URL_CLAUDE = 'api.anthropic.test/v1/messages';

beforeEach(fn () => Sleep::fake());

function claude(): ClaudeClientIA
{
    return new ClaudeClientIA(app(Factory::class), [
        'api_key' => 'cle-test', 'modele' => 'claude-haiku-5-5', 'url' => 'https://api.anthropic.test/v1', 'version' => '2023-06-01',
        'effort' => 'low', 'max_tokens' => 4000, 'tentatives' => 3, 'attente_ms' => 500, 'delai' => 30,
    ]);
}

function demandeClaude(): DemandeEnrichissement
{
    return new DemandeEnrichissement('emploi', 'Comptable confirmé', 'Poste de comptable au Plateau, 5 ans d’expérience.', 'SID', 'Plateau', ['Comptabilité', 'Commerce']);
}

/**
 * Réponse de l'API Messages : un bloc de réflexion (vide par défaut) puis le JSON dans un bloc texte.
 *
 * @param  array<string, mixed>|string  $sortie
 */
function reponseClaude(array|string $sortie, string $arret = 'end_turn'): array
{
    return [
        'id' => 'msg_01', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => is_string($sortie) ? $sortie : json_encode($sortie)],
        ],
        'stop_reason' => $arret,
        'usage' => ['input_tokens' => 412, 'output_tokens' => 96],
    ];
}

function sortieValide(array $surcharge = []): array
{
    return array_replace([
        'texte_carte' => 'Une PME du Plateau recrute un comptable confirmé, 5 ans d’expérience.',
        'categorie' => 'Comptabilité',
        'score_fiabilite' => 82,
        'signaux_risque' => [],
    ], $surcharge);
}

it('envoie la requête Messages attendue et lit le bloc texte après la réflexion', function () {
    Http::fake([URL_CLAUDE => Http::response(reponseClaude(sortieValide(['signaux_risque' => ['informations_vagues']])))]);

    $resultat = claude()->enrichir(demandeClaude());

    expect($resultat->texteCarte)->toStartWith('Une PME du Plateau')
        ->and($resultat->categorie)->toBe('Comptabilité')
        ->and($resultat->scoreFiabilite)->toBe(82)
        ->and($resultat->signauxRisque)->toBe(['informations_vagues'])
        ->and($resultat->modele)->toBe('claude-haiku-5-5')
        ->and([$resultat->tokensEntree, $resultat->tokensSortie])->toBe([412, 96]);

    Http::assertSent(function (RequeteHttp $requete): bool {
        $schema = $requete['output_config']['format']['schema'];

        return $requete->url() === 'https://api.anthropic.test/v1/messages'
            && $requete->hasHeader('x-api-key', 'cle-test')
            && $requete->hasHeader('anthropic-version', '2023-06-01')
            && $requete['model'] === 'claude-haiku-5-5'
            && $requete['max_tokens'] === 4000
            && $requete['output_config']['effort'] === 'low'
            && $requete['output_config']['format']['type'] === 'json_schema'
            && $schema['additionalProperties'] === false
            && $schema['required'] === ['texte_carte', 'categorie', 'score_fiabilite', 'signaux_risque']
            && $schema['properties']['categorie']['anyOf'][0]['enum'] === ['Comptabilité', 'Commerce']
            && ! isset($requete['thinking']) && ! isset($requete['temperature'])
            && str_contains($requete['messages'][0]['content'], '<annonce>');
    });
});

it('refuse une réponse invalide', function (array|string $sortie, string $arret, string $message) {
    Http::fake([URL_CLAUDE => Http::response(reponseClaude($sortie, $arret))]);

    expect(fn () => claude()->enrichir(demandeClaude()))->toThrow(ReponseIAInvalideException::class, $message);
})->with([
    'JSON cassé' => ['{"texte_carte": "abc"', 'end_turn', 'sans JSON valide'],
    'score hors bornes' => [sortieValide(['score_fiabilite' => 150]), 'end_turn', 'hors du format'],
    'score décimal' => [sortieValide(['score_fiabilite' => 82.5]), 'end_turn', 'hors du format'],
    'signal inconnu' => [sortieValide(['signaux_risque' => ['inventé']]), 'end_turn', 'hors du format'],
    'catégorie hors liste' => [sortieValide(['categorie' => 'Astronomie']), 'end_turn', 'hors du format'],
    'texte vide' => [sortieValide(['texte_carte' => '  ']), 'end_turn', 'hors du format'],
    'clé manquante' => [['texte_carte' => 'abc', 'score_fiabilite' => 50], 'end_turn', 'hors du format'],
    'refus' => [sortieValide(), 'refusal', 'refusé'],
    'tronquée' => [sortieValide(), 'max_tokens', 'tronquée'],
]);

it('rejoue un 429 en respectant retry-after, puis un 529 avec une attente croissante', function () {
    Http::fakeSequence(URL_CLAUDE)
        ->push(['type' => 'error', 'error' => ['type' => 'rate_limit_error']], 429, ['retry-after' => '2'])
        ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)
        ->push(reponseClaude(sortieValide()));

    expect(claude()->enrichir(demandeClaude())->scoreFiabilite)->toBe(82);

    Http::assertSentCount(3);
    Sleep::assertSlept(fn ($duree): bool => (int) $duree->totalMilliseconds === 2000, 1);
    Sleep::assertSlept(fn ($duree): bool => (int) $duree->totalMilliseconds === 1000, 1);
});

it('abandonne après le nombre de tentatives prévu', function () {
    Http::fake([URL_CLAUDE => Http::response(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)]);

    expect(fn () => claude()->enrichir(demandeClaude()))->toThrow(RequestException::class);
    Http::assertSentCount(3);
});

it('ne rejoue pas une requête refusée (400)', function () {
    Http::fake([URL_CLAUDE => Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error']], 400)]);

    expect(fn () => claude()->enrichir(demandeClaude()))->toThrow(RequestException::class);
    Http::assertSentCount(1);
});

it('est choisi par IA_DRIVER=claude', function () {
    config(['rezohelp.ia.driver' => 'claude']);
    app()->forgetInstance(ClientIA::class);

    expect(app(ClientIA::class))->toBeInstanceOf(ClaudeClientIA::class);
});
