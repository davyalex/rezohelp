<?php

namespace App\Services\Paiement;

use App\Contracts\PasserellePaiement;
use App\Donnees\DemandePaiement;
use App\Donnees\InitialisationPaiement;
use App\Donnees\VerificationPaiement;
use App\Enums\StatutPaiementPasserelle;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CinetPay (Orange Money, MTN MoMo, Moov Money, Wave, carte) — API checkout v2.
 *
 * - initialiser : POST /payment → URL du guichet ;
 * - verifier : POST /payment/check (on ne croit jamais la seule notification) ;
 * - notification : en-tête x-token = HMAC-SHA256 des champs cpm_* avec la clé secrète.
 */
class CinetPayPasserelle implements PasserellePaiement
{
    /** Ordre des champs signés dans l'en-tête x-token (documentation CinetPay). */
    private const array CHAMPS_SIGNES = [
        'cpm_site_id', 'cpm_trans_id', 'cpm_trans_date', 'cpm_amount', 'cpm_currency', 'signature',
        'payment_method', 'cel_phone_num', 'cpm_phone_prefixe', 'cpm_language', 'cpm_version',
        'cpm_payment_config', 'cpm_page_action', 'cpm_custom', 'cpm_designation', 'cpm_error_message',
    ];

    /**
     * @param  array{api_key: ?string, site_id: ?string, secret: ?string, url: string}  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {}

    public function nom(): string
    {
        return 'cinetpay';
    }

    public function initialiser(DemandePaiement $demande): InitialisationPaiement
    {
        $reponse = $this->http->acceptJson()->timeout(20)->post($this->url('payment'), [
            'apikey' => $this->config['api_key'],
            'site_id' => $this->config['site_id'],
            'transaction_id' => $demande->reference,
            'amount' => $demande->montantFcfa,
            'currency' => 'XOF',
            'description' => mb_substr($demande->description, 0, 100),
            'notify_url' => $demande->urlNotification,
            'return_url' => $demande->urlRetour,
            'channels' => 'ALL',
            'lang' => 'fr',
            'customer_name' => $demande->nomClient,
            'customer_phone_number' => $demande->telephoneClient,
        ]);

        $url = $reponse->json('data.payment_url');

        if (! $reponse->successful() || ! is_string($url) || $url === '') {
            throw new RuntimeException('CinetPay : initialisation refusée ('.$reponse->json('code', $reponse->status()).' '.$reponse->json('message', '').').');
        }

        return new InitialisationPaiement(
            reference: $demande->reference,
            urlPaiement: $url,
            donnees: ['payment_token' => $reponse->json('data.payment_token')],
        );
    }

    public function verifier(string $reference): VerificationPaiement
    {
        $reponse = $this->http->acceptJson()->timeout(20)->post($this->url('payment/check'), [
            'apikey' => $this->config['api_key'],
            'site_id' => $this->config['site_id'],
            'transaction_id' => $reference,
        ]);

        $donnees = (array) $reponse->json('data', []);
        $statut = match (strtoupper((string) ($donnees['status'] ?? ''))) {
            'ACCEPTED' => StatutPaiementPasserelle::Accepte,
            'REFUSED', 'CANCELED', 'CANCELLED' => StatutPaiementPasserelle::Refuse,
            default => StatutPaiementPasserelle::EnAttente,
        };

        return new VerificationPaiement(
            reference: $reference,
            statut: $statut,
            montantFcfa: isset($donnees['amount']) && is_numeric($donnees['amount']) ? (int) $donnees['amount'] : null,
            moyenPaiement: isset($donnees['payment_method']) ? strtolower((string) $donnees['payment_method']) : null,
            donnees: ['code' => $reponse->json('code'), 'message' => $reponse->json('message'), 'data' => $donnees],
        );
    }

    public function signatureValide(Request $request): bool
    {
        $jeton = (string) $request->header('x-token', '');
        $secret = (string) $this->config['secret'];

        if ($jeton === '' || $secret === '') {
            return false;
        }

        $donnees = implode('', array_map(fn (string $champ): string => (string) $request->input($champ, ''), self::CHAMPS_SIGNES));

        return hash_equals(hash_hmac('sha256', $donnees, $secret), $jeton);
    }

    public function referenceNotifiee(Request $request): ?string
    {
        $reference = $request->input('cpm_trans_id');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * Calcule le x-token d'une notification (outil de test et de recette).
     *
     * @param  array<string, string>  $champs
     */
    public function signer(array $champs): string
    {
        $donnees = implode('', array_map(fn (string $champ): string => $champs[$champ] ?? '', self::CHAMPS_SIGNES));

        return hash_hmac('sha256', $donnees, (string) $this->config['secret']);
    }

    private function url(string $chemin): string
    {
        return rtrim($this->config['url'], '/').'/'.$chemin;
    }
}
