<?php

use App\Contracts\ClientIA;
use App\Contracts\FournisseurSms;
use App\Contracts\PasserellePaiement;
use App\Contracts\SourceVeille;
use App\Services\IA\FakeClientIA;
use App\Services\Paiement\FakePasserelle;
use App\Services\Sms\LogFournisseurSms;
use App\Services\Veille\FakeSourceVeille;

it('lie chaque interface au driver fake ou log en test', function () {
    expect(app(PasserellePaiement::class))->toBeInstanceOf(FakePasserelle::class)
        ->and(app(FournisseurSms::class))->toBeInstanceOf(LogFournisseurSms::class)
        ->and(app(SourceVeille::class))->toBeInstanceOf(FakeSourceVeille::class)
        ->and(app(ClientIA::class))->toBeInstanceOf(FakeClientIA::class);
});

it('refuse un driver inconnu', function (string $service, string $interface) {
    config(["rezohelp.{$service}.driver" => 'inexistant']);
    app()->forgetInstance($interface);

    app($interface);
})->with([
    'paiement' => ['paiement', PasserellePaiement::class],
    'sms' => ['sms', FournisseurSms::class],
    'veille' => ['veille', SourceVeille::class],
    'ia' => ['ia', ClientIA::class],
])->throws(InvalidArgumentException::class, 'inexistant');
