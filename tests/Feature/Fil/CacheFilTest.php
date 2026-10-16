<?php

use App\Actions\Publication\BasculerJaime;
use App\Donnees\FiltresFil;
use App\Enums\StatutPublication;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\CacheFil;
use App\Services\FilService;
use Database\Seeders\ParametresSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(ParametresSeeder::class);
    $this->cache = app(CacheFil::class);
    $this->fil = app(FilService::class);
});

/**
 * @return list<int>
 */
function idsPremiereSalve(): array
{
    return app(FilService::class)->salve(new FiltresFil)->publications->pluck('id')->all();
}

it('met la salve en cache sous une clé versionnée', function () {
    $publication = Publication::factory()->publiee()->create();
    $version = $this->cache->versionFil();

    expect(idsPremiereSalve())->toBe([$publication->id])
        ->and(Cache::has("fil:v{$version}:tout:toutes:".(new FiltresFil)->empreinte().':debut'))->toBeTrue();

    // Une modification en base hors observer n'apparaît pas : c'est bien le cache qui répond.
    Publication::query()->whereKey($publication->id)->toBase()->update(['statut' => StatutPublication::EnAttente->value]);
    expect(idsPremiereSalve())->toBe([$publication->id]);
});

it('invalide le fil quand une publication est publiée', function () {
    $ancienne = Publication::factory()->publiee(now()->subHour())->create();
    expect(idsPremiereSalve())->toBe([$ancienne->id]);
    $version = $this->cache->versionFil();

    $nouvelle = Publication::factory()->enAttente()->create();
    expect($this->cache->versionFil())->toBe($version);

    $nouvelle->forceFill(['statut' => StatutPublication::Publiee, 'publiee_le' => now(), 'tri_le' => now()])->save();

    expect($this->cache->versionFil())->toBe($version + 1)
        ->and(idsPremiereSalve())->toBe([$nouvelle->id, $ancienne->id]);
});

it('invalide le fil quand une publication est dépubliée ou supprimée', function () {
    $a = Publication::factory()->publiee(now()->subHour())->create();
    $b = Publication::factory()->publiee(now()->subHours(2))->create();
    expect(idsPremiereSalve())->toBe([$a->id, $b->id]);

    $a->statut = StatutPublication::EnAttente;
    $a->save();
    expect(idsPremiereSalve())->toBe([$b->id]);

    $b->delete();
    expect(idsPremiereSalve())->toBe([]);
});

it('n’invalide ni le fil ni la carte quand le compteur de J’aime change', function () {
    $publication = Publication::factory()->publiee()->create();
    $modifieLe = $publication->modifie_le?->getTimestamp();
    $version = $this->cache->versionFil();

    app(BasculerJaime::class)->executer(Utilisateur::factory()->create(), $publication);

    $publication->refresh();
    expect($publication->nb_jaime)->toBe(1)
        ->and($publication->modifie_le?->getTimestamp())->toBe($modifieLe)
        ->and($this->cache->versionFil())->toBe($version);
});

it('ne met jamais de données propres au visiteur dans le cache partagé', function () {
    $publication = Publication::factory()->publiee()->create(['nb_jaime' => 3]);
    $fan = Utilisateur::factory()->create();
    app(BasculerJaime::class)->executer($fan, $publication);
    $fan->publicationsEnregistrees()->attach($publication->id, ['cree_le' => now()]);

    $pageDuFan = (string) $this->actingAs($fan)->get('/')->assertOk()->getContent();
    $gabarit = (string) Cache::get('carte:'.$publication->id.':'.$publication->refresh()->modifie_le?->getTimestamp());

    expect($pageDuFan)->toContain('aria-pressed="true"')
        ->and($gabarit)->not->toBe('')
        // Le gabarit partagé ne contient que des jetons : ni état, ni compteur, ni jeton CSRF.
        ->and($gabarit)->toContain('⟦aime⟧')->toContain('⟦enregistre⟧')->toContain('⟦csrf⟧')->toContain('⟦nb_jaime⟧')
        ->and($gabarit)->not->toContain('aria-pressed="true"')
        ->and($gabarit)->not->toContain(session()->token());

    // Un autre visiteur voit la même carte, sans l'état du premier.
    auth()->logout();
    $pageAutre = (string) $this->actingAs(Utilisateur::factory()->create())->get('/')->getContent();
    expect($pageAutre)->not->toContain('aria-pressed="true"')
        ->and($pageAutre)->toContain('>4<');
});

it('remplace la date relative à chaque affichage', function () {
    $publication = Publication::factory()->publiee(now()->subHours(2))->create();

    $this->get('/')->assertSee('il y a 2 h');
    $this->travel(1)->hours();
    $this->cache->invaliderFil();

    $this->get('/')->assertSee('il y a 3 h');
    expect($publication->refresh()->statut)->toBe(StatutPublication::Publiee);
});
