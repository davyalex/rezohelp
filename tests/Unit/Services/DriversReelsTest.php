<?php

use App\Donnees\DemandePaiement;
use App\Enums\StatutPaiementPasserelle;
use App\Services\CacheFil;
use App\Services\Paiement\CinetPayPasserelle;
use App\Services\Sms\OrangeFournisseurSms;
use App\Services\Sms\TwilioFournisseurSms;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function cinetpay(): CinetPayPasserelle
{
    return new CinetPayPasserelle(app(Factory::class), [
        'api_key' => 'cle-test', 'site_id' => '105', 'secret' => 'secret-test', 'url' => 'https://api-checkout.cinetpay.test/v2',
    ]);
}

function demandeCinetPay(): DemandePaiement
{
    return new DemandePaiement('CMD-2026-000007', 1000, 'Resohelph — Alertes SMS 30 jours', 'https://resohelph.test/retour/7',
        'https://resohelph.test/paiements/notification/cinetpay', '+2250708091011', 'Awa Koné');
}

it('CinetPay : ouvre le guichet avec le montant, la référence et les URL', function () {
    Http::fake(['api-checkout.cinetpay.test/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://checkout.cinetpay.test/abc', 'payment_token' => 'jeton']])]);

    $initialisation = cinetpay()->initialiser(demandeCinetPay());

    expect($initialisation->urlPaiement)->toBe('https://checkout.cinetpay.test/abc')
        ->and($initialisation->donnees['payment_token'])->toBe('jeton');
    Http::assertSent(fn (RequeteHttp $requete): bool => $requete['transaction_id'] === 'CMD-2026-000007'
        && $requete['amount'] === 1000 && $requete['currency'] === 'XOF' && $requete['site_id'] === '105'
        && $requete['notify_url'] === 'https://resohelph.test/paiements/notification/cinetpay');
});

it('CinetPay : signale un refus d’initialisation', function () {
    Http::fake(['api-checkout.cinetpay.test/*' => Http::response(['code' => '608', 'message' => 'MINIMUM_REQUIRED_FIELDS'], 400)]);

    expect(fn () => cinetpay()->initialiser(demandeCinetPay()))->toThrow(RuntimeException::class, '608');
});

it('CinetPay : revérifie le statut d’une transaction', function (string $statut, StatutPaiementPasserelle $attendu) {
    Http::fake(['api-checkout.cinetpay.test/v2/payment/check' => Http::response([
        'code' => '00', 'data' => ['status' => $statut, 'amount' => '1000', 'payment_method' => 'OM'],
    ])]);

    $verification = cinetpay()->verifier('CMD-2026-000007');

    expect($verification->statut)->toBe($attendu)
        ->and($verification->montantFcfa)->toBe(1000)
        ->and($verification->moyenPaiement)->toBe('om');
})->with([
    ['ACCEPTED', StatutPaiementPasserelle::Accepte],
    ['REFUSED', StatutPaiementPasserelle::Refuse],
    ['WAITING_FOR_CUSTOMER', StatutPaiementPasserelle::EnAttente],
]);

it('CinetPay : vérifie le jeton x-token de la notification', function () {
    $champs = ['cpm_site_id' => '105', 'cpm_trans_id' => 'CMD-2026-000007', 'cpm_amount' => '1000', 'cpm_currency' => 'XOF'];
    $passerelle = cinetpay();

    $valide = Request::create('/paiements/notification/cinetpay', 'POST', $champs, server: ['HTTP_X_TOKEN' => $passerelle->signer($champs)]);
    $falsifiee = Request::create('/paiements/notification/cinetpay', 'POST', [...$champs, 'cpm_amount' => '10'], server: ['HTTP_X_TOKEN' => $passerelle->signer($champs)]);
    $sansJeton = Request::create('/paiements/notification/cinetpay', 'POST', $champs);

    expect($passerelle->signatureValide($valide))->toBeTrue()
        ->and($passerelle->signatureValide($falsifiee))->toBeFalse()
        ->and($passerelle->signatureValide($sansJeton))->toBeFalse()
        ->and($passerelle->referenceNotifiee($valide))->toBe('CMD-2026-000007');
});

function orange(): OrangeFournisseurSms
{
    return new OrangeFournisseurSms(app(Factory::class), app(CacheFil::class), [
        'client_id' => 'id', 'client_secret' => 'secret', 'expediteur' => '+2250000', 'nom_expediteur' => 'Resohelph',
        'url' => 'https://api.orange.test', 'cout_fcfa' => 25,
    ]);
}

it('Orange : obtient un jeton OAuth, le réutilise, et envoie le SMS', function () {
    Http::fake([
        'api.orange.test/oauth/v3/token' => Http::response(['access_token' => 'jeton-orange', 'expires_in' => 3600]),
        'api.orange.test/smsmessaging/*' => Http::response(['outboundSMSMessageRequest' => ['resourceURL' => 'https://api.orange.test/requests/ref-42']], 201),
    ]);

    $premier = orange()->envoyer('+2250708091011', 'Resohelph: test');
    $second = orange()->envoyer('+2250708091011', 'Resohelph: test 2');

    expect($premier->envoye)->toBeTrue()
        ->and($premier->reference)->toBe('ref-42')
        ->and($premier->coutFcfa)->toBe(25)
        ->and($second->envoye)->toBeTrue();
    Http::assertSentCount(3);
    Http::assertSent(fn (RequeteHttp $requete): bool => str_contains($requete->url(), '/outbound/tel%3A%2B2250000/requests')
        && $requete->hasHeader('Authorization', 'Bearer jeton-orange')
        && $requete['outboundSMSMessageRequest']['address'] === 'tel:+2250708091011');
});

it('Orange : renvoie un échec sans lever d’exception', function () {
    Http::fake([
        'api.orange.test/oauth/v3/token' => Http::response(['access_token' => 'jeton-orange']),
        'api.orange.test/smsmessaging/*' => Http::response(['requestError' => 'quota'], 403),
    ]);

    $resultat = orange()->envoyer('+2250708091011', 'Resohelph: test');

    expect($resultat->envoye)->toBeFalse()->and($resultat->erreur)->toContain('Orange 403');
});

function twilio(): TwilioFournisseurSms
{
    return new TwilioFournisseurSms(app(Factory::class), [
        'sid' => 'AC123', 'token' => 'jeton', 'expediteur' => '+15550000', 'url' => 'https://api.twilio.test/2010-04-01', 'cout_fcfa' => 50,
    ]);
}

it('Twilio : envoie le SMS en authentification basique', function () {
    Http::fake(['api.twilio.test/*' => Http::response(['sid' => 'SM42'], 201)]);

    $resultat = twilio()->envoyer('+2250708091011', 'Resohelph: test');

    expect($resultat->envoye)->toBeTrue()->and($resultat->reference)->toBe('SM42')->and($resultat->coutFcfa)->toBe(50);
    Http::assertSent(fn (RequeteHttp $requete): bool => $requete->url() === 'https://api.twilio.test/2010-04-01/Accounts/AC123/Messages.json'
        && $requete->hasHeader('Authorization', 'Basic '.base64_encode('AC123:jeton'))
        && $requete['To'] === '+2250708091011' && $requete['Body'] === 'Resohelph: test');
});

it('Twilio : renvoie un échec lisible', function () {
    Http::fake(['api.twilio.test/*' => Http::response(['message' => 'Invalid To number'], 400)]);

    $resultat = twilio()->envoyer('+2250708091011', 'Resohelph: test');

    expect($resultat->envoye)->toBeFalse()->and($resultat->erreur)->toBe('Twilio 400 : Invalid To number');
});
