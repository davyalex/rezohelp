<?php

namespace Tests\Support;

use App\Contracts\FournisseurSms;
use App\Donnees\ResultatEnvoiSms;

/**
 * Fournisseur de SMS des tests : garde les messages en mémoire.
 */
class FournisseurSmsEnMemoire implements FournisseurSms
{
    /**
     * @var list<array{telephone: string, message: string}>
     */
    public array $envoyes = [];

    public function nom(): string
    {
        return 'log';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        $this->envoyes[] = ['telephone' => $telephone, 'message' => $message];

        return new ResultatEnvoiSms(envoye: true, reference: 'TEST-'.count($this->envoyes), coutFcfa: 25);
    }

    /**
     * Dernier code à 6 chiffres envoyé à ce numéro.
     */
    public function dernierCode(string $telephone): ?string
    {
        foreach (array_reverse($this->envoyes) as $sms) {
            if ($sms['telephone'] === $telephone && preg_match('/\b(\d{6})\b/', $sms['message'], $resultat) === 1) {
                return $resultat[1];
            }
        }

        return null;
    }
}
