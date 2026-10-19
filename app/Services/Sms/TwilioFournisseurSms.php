<?php

namespace App\Services\Sms;

use App\Contracts\FournisseurSms;
use App\Donnees\ResultatEnvoiSms;
use Illuminate\Http\Client\Factory;
use Throwable;

/**
 * Twilio : POST /Accounts/{sid}/Messages.json en authentification basique.
 */
class TwilioFournisseurSms implements FournisseurSms
{
    /**
     * @param  array{sid: ?string, token: ?string, expediteur: ?string, url: string, cout_fcfa: int}  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {}

    public function nom(): string
    {
        return 'twilio';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        try {
            $reponse = $this->http->asForm()
                ->withBasicAuth((string) $this->config['sid'], (string) $this->config['token'])
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($this->config['url'], '/').'/Accounts/'.$this->config['sid'].'/Messages.json', [
                    'To' => $telephone,
                    'From' => $this->config['expediteur'],
                    'Body' => $message,
                ]);

            if (! $reponse->successful()) {
                return new ResultatEnvoiSms(envoye: false, erreur: 'Twilio '.$reponse->status().' : '.(string) $reponse->json('message', $reponse->body()));
            }

            return new ResultatEnvoiSms(envoye: true, reference: (string) $reponse->json('sid'), coutFcfa: $this->config['cout_fcfa']);
        } catch (Throwable $erreur) {
            return new ResultatEnvoiSms(envoye: false, erreur: $erreur->getMessage());
        }
    }
}
