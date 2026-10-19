<?php

use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Plan;
use App\Models\PlanLimite;
use App\Models\Utilisateur;
use App\Notifications\FinAbonnementProche;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    referentielsPublication();
    Notification::fake();
    $this->membre = Utilisateur::factory()->create();
    $this->plan = Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail();
});

function abonnementSms(Utilisateur $membre, Plan $plan, array $attributs = []): Abonnement
{
    return Abonnement::factory()->create(array_merge([
        'titulaire_id' => $membre->id,
        'plan_id' => $plan->id,
        'debut_le' => now()->subDays(27),
        'fin_le' => now()->addDays(3)->setTime(12, 0),
    ], $attributs));
}

it('envoie chaque relance J-3, J-1 et J0 une seule fois', function () {
    $this->travelTo(now()->setTime(9, 0));
    $abonnement = abonnementSms($this->membre, $this->plan);

    $this->artisan('abonnements:traiter')->assertSuccessful();
    $this->artisan('abonnements:traiter')->assertSuccessful();
    Notification::assertSentToTimes($this->membre, FinAbonnementProche::class, 1);

    $this->travel(2)->days();
    $this->artisan('abonnements:traiter');
    $this->travel(1)->days();
    $this->artisan('abonnements:traiter');

    Notification::assertSentToTimes($this->membre, FinAbonnementProche::class, 3);
    expect($abonnement->refresh()->relances_envoyees)->toBe(['j3', 'j1', 'j0']);
});

it('ne relance pas un abonnement déjà renouvelé', function () {
    $abonnement = abonnementSms($this->membre, $this->plan);
    abonnementSms($this->membre, $this->plan, ['debut_le' => $abonnement->fin_le, 'fin_le' => $abonnement->fin_le?->copy()->addDays(30)]);

    $this->artisan('abonnements:traiter');

    Notification::assertNothingSent();
});

it('met un pass payé en grâce, puis l’expire après jours_grace', function () {
    $abonnement = abonnementSms($this->membre, $this->plan, ['fin_le' => now()->subHour()]);

    $this->artisan('abonnements:traiter');
    expect($abonnement->refresh()->statut)->toBe(StatutAbonnement::Grace);

    $this->travel(3)->days();
    $this->artisan('abonnements:traiter');
    expect($abonnement->refresh()->statut)->toBe(StatutAbonnement::Expire);
});

it('expire un essai directement, sans délai de grâce', function () {
    $essai = Abonnement::factory()->essai()->create([
        'titulaire_id' => $this->membre->id, 'plan_id' => $this->plan->id, 'fin_le' => now()->subMinute(),
    ]);

    $this->artisan('abonnements:traiter');

    expect($essai->refresh()->statut)->toBe(StatutAbonnement::Expire);
});

it('met en pause les alertes au-delà de la limite revenue au plan par défaut, en gardant les plus anciennes', function () {
    parametre('quotas_actifs', '1');
    $plus = Plan::factory()->create(['cible' => 'membre']);
    PlanLimite::factory()->create(['plan_id' => $plus->id, 'cle' => 'alertes.max', 'valeur' => '20']);
    $abonnement = abonnementSms($this->membre, $plus, ['statut' => StatutAbonnement::Essai, 'fin_le' => now()->subMinute()]);

    $alertes = collect(range(1, 12))->map(fn (int $i) => Alerte::factory()->create([
        'utilisateur_id' => $this->membre->id, 'active' => true, 'cree_le' => now()->subDays(20 - $i),
    ]));

    $this->artisan('abonnements:traiter');

    expect($abonnement->refresh()->statut)->toBe(StatutAbonnement::Expire)
        ->and(Alerte::query()->where('active', true)->pluck('id')->sort()->values()->all())
        ->toBe($alertes->take(10)->pluck('id')->sort()->values()->all());
});
