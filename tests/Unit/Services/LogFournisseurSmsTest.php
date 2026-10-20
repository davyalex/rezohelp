<?php

use App\Services\Sms\LogFournisseurSms;
use Psr\Log\AbstractLogger;

it('écrit le SMS dans le journal et renvoie une référence', function () {
    $journal = new class extends AbstractLogger
    {
        /** @var list<array{message: string, contexte: array<string, mixed>}> */
        public array $lignes = [];

        public function log($level, Stringable|string $message, array $context = []): void
        {
            $this->lignes[] = ['message' => (string) $message, 'contexte' => $context];
        }
    };

    $resultat = (new LogFournisseurSms($journal))->envoyer('+2250708091011', 'Rezohelp: votre code est 123456');

    expect($resultat->envoye)->toBeTrue()
        ->and($resultat->reference)->toStartWith('LOG-')
        ->and($resultat->coutFcfa)->toBe(0)
        ->and($journal->lignes)->toHaveCount(1)
        ->and($journal->lignes[0]['contexte']['telephone'])->toBe('+2250708091011')
        ->and($journal->lignes[0]['contexte']['message'])->toBe('Rezohelp: votre code est 123456');
});
