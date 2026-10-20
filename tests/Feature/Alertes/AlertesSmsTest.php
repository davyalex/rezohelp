<?php

use App\Actions\Sms\PlanifierSms;
use App\Enums\MotifSms;
use App\Enums\TypePublication;
use App\Jobs\CorrespondreAlertes;
use App\Jobs\EnvoyerSms;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\Publication;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    referentielsPublication();
    $this->sms = fauxSms();
    $this->travelTo(now()->setTime(10, 0));
    $this->cocody = Commune::query()->where('slug', 'cocody')->firstOrFail();
    $this->membre = Utilisateur::factory()->create();
    $this->alerte = Alerte::factory()->create(['utilisateur_id' => $this->membre->id, 'type' => TypePublication::Service, 'commune_id' => null, 'categorie_id' => null, 'mots_cles' => null, 'type_contrat' => null]);
});

function donnerCanalSms(Utilisateur $membre): void
{
    Abonnement::factory()->create([
        'titulaire_id' => $membre->id,
        'plan_id' => Plan::query()->where('code', 'alertes_sms_30j')->firstOrFail()->id,
    ]);
}

function nouvelleCorrespondance(Commune $commune): Publication
{
    $publication = Publication::factory()->publiee()->create([
        'type' => TypePublication::Service, 'sous_type' => 'offre', 'titre' => 'Électricien disponible à Cocody', 'commune_id' => $commune->id,
    ]);
    CorrespondreAlertes::dispatchSync($publication->id);

    return $publication;
}

it('n’envoie pas de SMS sans le droit alertes.canal_sms', function () {
    nouvelleCorrespondance($this->cocody);

    expect($this->sms->envoyes)->toBe([])
        ->and($this->membre->notifications()->count())->toBe(1);
});

it('envoie l’alerte par SMS (GSM-7, lien court) quand le membre y a droit', function () {
    donnerCanalSms($this->membre);

    $publication = nouvelleCorrespondance($this->cocody);

    expect($this->sms->envoyes)->toHaveCount(1)
        ->and($this->sms->envoyes[0]['telephone'])->toBe($this->membre->telephone)
        ->and($this->sms->envoyes[0]['message'])->toBe('Rezohelp: Electricien disponible a Cocody, Cocody. Voir: '
            .preg_replace('#^https?://#', '', route('publications.court', $publication->code_court)))
        ->and(SmsEnvoye::query()->sole()->motif)->toBe(MotifSms::Alerte);
});

it('n’envoie jamais de SMS vers un numéro non vérifié', function () {
    donnerCanalSms($this->membre);
    $this->membre->forceFill(['telephone_verifie_le' => null])->save();

    nouvelleCorrespondance($this->cocody);

    expect($this->sms->envoyes)->toBe([]);
});

it('au-delà du plafond quotidien, garde les correspondances pour un seul récapitulatif SMS à 19h00', function () {
    donnerCanalSms($this->membre);

    foreach (range(1, 7) as $i) {
        nouvelleCorrespondance($this->cocody);
    }

    expect($this->sms->envoyes)->toHaveCount(5)
        ->and(DB::table('alertes_publications')->where('sms_en_attente', true)->count())->toBe(2)
        ->and($this->membre->notifications()->count())->toBe(7);

    $this->travelTo(now()->setTime(19, 0));
    $this->artisan('alertes:recap-sms')->assertSuccessful();

    expect($this->sms->envoyes)->toHaveCount(6)
        ->and($this->sms->envoyes[5]['message'])->toStartWith('Rezohelp: 2 autre(s) publication(s)')
        ->and(strlen($this->sms->envoyes[5]['message']))->toBeLessThanOrEqual(160)
        ->and(DB::table('alertes_publications')->where('sms_en_attente', true)->count())->toBe(0);

    // Rejouer la commande n'envoie rien de plus.
    $this->artisan('alertes:recap-sms');
    expect($this->sms->envoyes)->toHaveCount(6);
});

it('respecte le plafond même quand les SMS précédents sont encore dans la file', function () {
    Queue::fake([EnvoyerSms::class]);
    donnerCanalSms($this->membre);

    foreach (range(1, 7) as $i) {
        nouvelleCorrespondance($this->cocody);
    }

    expect(SmsEnvoye::query()->count())->toBe(5)
        ->and(DB::table('alertes_publications')->where('sms_en_attente', true)->count())->toBe(2);
    Queue::assertPushed(EnvoyerSms::class, 5);
});

it('diffère à 7h00 un SMS demandé pendant la plage silencieuse', function () {
    Queue::fake();
    $this->travelTo(now()->setTime(22, 30));

    app(PlanifierSms::class)->executer('+2250708091011', 'Bonsoir', MotifSms::Alerte, respecterPlageSilencieuse: true);

    Queue::assertPushed(EnvoyerSms::class, fn (EnvoyerSms $job): bool => $job->delay instanceof DateTimeInterface
        && $job->delay->format('Y-m-d H:i') === now()->addDay()->format('Y-m-d').' 07:00');
});

it('calcule la fin de la plage silencieuse (21h00 → 7h00)', function (string $heure, ?string $attendu) {
    $reprise = PlanifierSms::finPlageSilencieuse(now()->setTimeFromTimeString($heure));

    expect($reprise?->format('H:i d'))->toBe($attendu === null ? null : $attendu);
})->with([
    'avant 21h' => ['20:59', null],
    'à 21h' => ['21:00', '07:00 '.now()->addDay()->format('d')],
    'en pleine nuit' => ['03:15', '07:00 '.now()->format('d')],
    'à 7h' => ['07:00', null],
]);

it('n’applique pas la plage silencieuse aux codes OTP', function () {
    Queue::fake();
    $this->travelTo(now()->setTime(23, 0));

    app(PlanifierSms::class)->executer('+2250708091011', 'Votre code : 123456', MotifSms::Otp);

    Queue::assertPushed(EnvoyerSms::class, fn (EnvoyerSms $job): bool => $job->delay === null);
});
