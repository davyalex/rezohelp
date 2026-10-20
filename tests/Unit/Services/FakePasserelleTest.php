<?php

use App\Contracts\PasserellePaiement;
use App\Donnees\DemandePaiement;
use App\Enums\StatutPaiementPasserelle;
use App\Services\Paiement\FakePasserelle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->passerelle = new FakePasserelle(Storage::disk('local'), 'secret-test');
});

function demandePaiement(string $reference = 'CMD-2026-000001'): DemandePaiement
{
    return new DemandePaiement(
        reference: $reference,
        montantFcfa: 1000,
        description: 'Alertes SMS 30 jours',
        urlRetour: 'https://rezohelp.test/paiements/retour/1',
        urlNotification: 'https://rezohelp.test/paiements/notification/fake',
        telephoneClient: '+2250708091011',
        nomClient: 'Awa Koné',
    );
}

it('initialise un paiement en attente', function () {
    $initialisation = $this->passerelle->initialiser(demandePaiement());

    expect($initialisation->reference)->toBe('CMD-2026-000001')
        ->and($initialisation->urlPaiement)->toBe('https://rezohelp.test/paiements/retour/1');

    $verification = $this->passerelle->verifier('CMD-2026-000001');

    expect($verification->statut)->toBe(StatutPaiementPasserelle::EnAttente)
        ->and($verification->montantFcfa)->toBe(1000)
        ->and($verification->estAccepte())->toBeFalse();
});

it('relit le résultat simulé d’un paiement', function () {
    $this->passerelle->initialiser(demandePaiement());
    $this->passerelle->simuler('CMD-2026-000001', StatutPaiementPasserelle::Accepte, moyen: 'wave');

    $verification = $this->passerelle->verifier('CMD-2026-000001');

    expect($verification->estAccepte())->toBeTrue()
        ->and($verification->montantFcfa)->toBe(1000)
        ->and($verification->moyenPaiement)->toBe('wave');
});

it('considère une référence inconnue comme en attente', function () {
    $verification = $this->passerelle->verifier('INCONNUE');

    expect($verification->statut)->toBe(StatutPaiementPasserelle::EnAttente)
        ->and($verification->montantFcfa)->toBeNull();
});

it('vérifie la signature HMAC des notifications', function () {
    $corps = json_encode(['reference' => 'CMD-2026-000001']);

    $valide = Request::create('/paiements/notification/fake', 'POST', server: [
        'HTTP_X_SIGNATURE' => $this->passerelle->signer($corps),
        'CONTENT_TYPE' => 'application/json',
    ], content: $corps);

    $falsifiee = Request::create('/paiements/notification/fake', 'POST', server: [
        'HTTP_X_SIGNATURE' => 'signature-falsifiee',
        'CONTENT_TYPE' => 'application/json',
    ], content: $corps);

    $absente = Request::create('/paiements/notification/fake', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
    ], content: $corps);

    expect($this->passerelle->signatureValide($valide))->toBeTrue()
        ->and($this->passerelle->referenceNotifiee($valide))->toBe('CMD-2026-000001')
        ->and($this->passerelle->signatureValide($falsifiee))->toBeFalse()
        ->and($this->passerelle->signatureValide($absente))->toBeFalse();
});

it('est utilisable via le conteneur', function () {
    expect(app(PasserellePaiement::class)->nom())->toBe('fake');
});
