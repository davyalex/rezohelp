<?php

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Actions\Otp\VerifierCodeOtp;
use App\Enums\ObjetOtp;
use App\Enums\ResultatOtp;
use App\Models\CodeOtp;

beforeEach(function () {
    $this->sms = fauxSms();
    $this->envoyer = app(EnvoyerCodeOtp::class);
    $this->verifier = app(VerifierCodeOtp::class);
});

it('génère un code à 6 chiffres valable 10 minutes, stocké sous forme d’empreinte', function () {
    $codeOtp = $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $code = $this->sms->dernierCode('+2250708091011');

    expect($code)->toMatch('/^\d{6}$/')
        ->and($codeOtp->empreinte_code)->not->toBe($code)
        ->and($codeOtp->empreinte_code)->toBe(EnvoyerCodeOtp::empreinte($code))
        ->and($codeOtp->expire_le->diffInMinutes(now(), true))->toEqualWithDelta(10, 0.1)
        ->and($this->sms->envoyes[0]['message'])->toMatch('/^[\x20-\x7E]+$/')
        ->and(mb_strlen($this->sms->envoyes[0]['message']))->toBeLessThanOrEqual(160);
});

it('accepte le bon code une seule fois', function () {
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $code = $this->sms->dernierCode('+2250708091011');

    expect($this->verifier->executer('+2250708091011', ObjetOtp::Verification, $code))->toBe(ResultatOtp::Valide)
        ->and($this->verifier->executer('+2250708091011', ObjetOtp::Verification, $code))->toBe(ResultatOtp::Expire);
});

it('ne mélange pas les usages ni les numéros', function () {
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $code = $this->sms->dernierCode('+2250708091011');

    expect($this->verifier->executer('+2250708091011', ObjetOtp::Reinitialisation, $code))->toBe(ResultatOtp::Expire)
        ->and($this->verifier->executer('+2250102030405', ObjetOtp::Verification, $code))->toBe(ResultatOtp::Expire);
});

it('bloque le code après 5 essais, même si le dernier est juste', function () {
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $code = $this->sms->dernierCode('+2250708091011');
    $faux = $code === '000000' ? '111111' : '000000';

    $resultats = [];
    for ($i = 0; $i < 5; $i++) {
        $resultats[] = $this->verifier->executer('+2250708091011', ObjetOtp::Verification, $faux);
    }

    expect($resultats)->toBe([ResultatOtp::Invalide, ResultatOtp::Invalide, ResultatOtp::Invalide, ResultatOtp::Invalide, ResultatOtp::Bloque])
        ->and($this->verifier->executer('+2250708091011', ObjetOtp::Verification, $code))->toBe(ResultatOtp::Bloque)
        ->and(CodeOtp::query()->sole()->essais)->toBe(5);
});

it('refuse un code expiré', function () {
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $code = $this->sms->dernierCode('+2250708091011');

    $this->travel(10)->minutes();
    $this->travel(1)->seconds();

    expect($this->verifier->executer('+2250708091011', ObjetOtp::Verification, $code))->toBe(ResultatOtp::Expire);
});

it('annule le code précédent quand un nouveau est envoyé', function () {
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);
    $this->envoyer->executer('+2250708091011', ObjetOtp::Verification);

    expect(CodeOtp::query()->whereNull('utilise_le')->count())->toBe(1);
});
