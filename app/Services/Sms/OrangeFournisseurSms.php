<?php

namespace App\Services\Sms;

use App\Contracts\FournisseurSms;
use App\Donnees\ResultatEnvoiSms;
use App\Services\CacheFil;
use Illuminate\Http\Client\Factory;
use Throwable;

/**
 * Orange SMS API (Côte d'Ivoire) : jeton OAuth 2 « client credentials », puis envoi
 * vers /smsmessaging/v1/outbound/{expéditeur}/requests.
 */
class OrangeFournisseurSms implements FournisseurSms
{
    /** Le jeton Orange vaut 1 h ; on le renouvelle un peu avant. */
    private const int DUREE_JETON = 3300;

    /**
     * @param  array{client_id: ?string, client_secret: ?string, expediteur: ?string, nom_expediteur: ?string, url: string, cout_fcfa: int}  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly CacheFil $cache,
        private readonly array $config,
    ) {}

    public function nom(): string
    {
        return 'orange';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        try {
            $expediteur = 'tel:'.$this->config['expediteur'];
            $reponse = $this->http->withToken($this->jeton())
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($this->config['url'], '/').'/smsmessaging/v1/outbound/'.rawurlencode($expediteur).'/requests', [
                    'outboundSMSMessageRequest' => array_filter([
                        'address' => 'tel:'.$telephone,
                        'senderAddress' => $expediteur,
                        'senderName' => $this->config['nom_expediteur'],
                        'outboundSMSTextMessage' => ['message' => $message],
                    ]),
                ]);

            if (! $reponse->successful()) {
                return new ResultatEnvoiSms(envoye: false, erreur: 'Orange '.$reponse->status().' : '.$reponse->body());
            }

            $lien = (string) $reponse->json('outboundSMSMessageRequest.resourceURL', '');

            return new ResultatEnvoiSms(envoye: true, reference: basename($lien) ?: null, coutFcfa: $this->config['cout_fcfa']);
        } catch (Throwable $erreur) {
            return new ResultatEnvoiSms(envoye: false, erreur: $erreur->getMessage());
        }
    }

    private function jeton(): string
    {
        return $this->cache->jeton('orange_sms', self::DUREE_JETON, function (): string {
            $reponse = $this->http->asForm()
                ->withBasicAuth((string) $this->config['client_id'], (string) $this->config['client_secret'])
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($this->config['url'], '/').'/oauth/v3/token', ['grant_type' => 'client_credentials'])
                ->throw();

            return (string) $reponse->json('access_token');
        });
    }
}
