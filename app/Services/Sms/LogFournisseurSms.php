<?php

namespace App\Services\Sms;

use App\Contracts\FournisseurSms;
use App\Donnees\ResultatEnvoiSms;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Fournisseur de SMS qui écrit les messages dans storage/logs/sms.log.
 */
class LogFournisseurSms implements FournisseurSms
{
    public function __construct(private readonly LoggerInterface $journal) {}

    public function nom(): string
    {
        return 'log';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        $reference = 'LOG-'.Str::upper(Str::random(12));

        $this->journal->info('SMS vers {telephone} : {message}', [
            'telephone' => $telephone,
            'message' => $message,
            'reference' => $reference,
        ]);

        return new ResultatEnvoiSms(envoye: true, reference: $reference);
    }
}
