<?php

use App\Donnees\FiltresFil;
use App\Models\Publication;
use App\Services\FilService;
use Database\Seeders\ParametresSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(ParametresSeeder::class));

/**
 * Lit tout le fil, salve par salve, en suivant les curseurs.
 *
 * @return list<list<int>>
 */
function lireToutLeFil(FilService $fil, ?callable $entreDeuxSalves = null): array
{
    $salves = [];
    $curseur = null;

    do {
        $salve = $fil->salve(new FiltresFil, $curseur);
        $salves[] = $salve->publications->pluck('id')->all();
        $curseur = $salve->curseurSuivant;

        if ($entreDeuxSalves !== null) {
            $entreDeuxSalves(count($salves));
        }
    } while ($curseur !== null);

    return $salves;
}

it('découpe le fil en salves de 12 triées par tri_le puis id, sans doublon ni trou', function () {
    $base = Carbon::parse('2026-10-10 08:00:00');
    // Des dates identiques départagent par id.
    foreach (range(1, 30) as $i) {
        Publication::factory()->publiee($base->copy()->addMinutes(intdiv($i, 2)))->create();
    }
    Publication::factory()->count(3)->enAttente()->create();

    $salves = lireToutLeFil(app(FilService::class));
    $ids = array_merge(...$salves);

    $attendus = Publication::query()->publiees()->orderByDesc('tri_le')->orderByDesc('id')->pluck('id')->all();

    expect(array_map('count', $salves))->toBe([12, 12, 6])
        ->and($ids)->toBe($attendus)
        ->and($ids)->toHaveCount(30)
        ->and(array_unique($ids))->toHaveCount(30);
});

it('ne produit ni doublon ni trou quand une publication paraît entre deux salves', function () {
    foreach (range(1, 25) as $i) {
        Publication::factory()->publiee(now()->subHours($i))->create();
    }
    $initiaux = Publication::query()->pluck('id')->all();

    $salves = lireToutLeFil(app(FilService::class), function (int $numero): void {
        if ($numero === 1) {
            Publication::factory()->publiee(now())->create();
        }
    });
    $ids = array_merge(...$salves);

    expect($ids)->toHaveCount(25)
        ->and(array_unique($ids))->toHaveCount(25)
        ->and($ids)->toEqualCanonicalizing($initiaux);
});

it('remonte une publication dont tri_le change sans la répéter plus loin', function () {
    foreach (range(1, 20) as $i) {
        Publication::factory()->publiee(now()->subHours($i))->create();
    }
    $ancienne = Publication::query()->orderBy('tri_le')->firstOrFail();

    $ancienne->forceFill(['tri_le' => now()->addMinute()])->save();
    $ids = array_merge(...lireToutLeFil(app(FilService::class)));

    expect($ids[0])->toBe($ancienne->id)
        ->and(array_count_values($ids)[$ancienne->id])->toBe(1);
});

it('exclut les publications non publiées et supprimées', function () {
    $visible = Publication::factory()->publiee()->create();
    Publication::factory()->enAttente()->create();
    Publication::factory()->create();
    Publication::factory()->publiee()->create()->delete();

    $ids = array_merge(...lireToutLeFil(app(FilService::class)));

    expect($ids)->toBe([$visible->id]);
});
