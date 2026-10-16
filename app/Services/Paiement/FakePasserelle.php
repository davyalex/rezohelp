<?php

namespace App\Services\Paiement;

use App\Contracts\PasserellePaiement;
use App\Donnees\DemandePaiement;
use App\Donnees\InitialisationPaiement;
use App\Donnees\VerificationPaiement;
use App\Enums\StatutPaiementPasserelle;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;

/**
 * Passerelle simulée (local et tests).
 *
 * L'état de chaque transaction est conservé dans storage/app/paiements-fake,
 * pour être relu d'une requête à l'autre comme une vraie API.
 */
class FakePasserelle implements PasserellePaiement
{
    public const string ENTETE_SIGNATURE = 'X-Signature';

    private const string DOSSIER = 'paiements-fake';

    public function __construct(
        private readonly Filesystem $disque,
        private readonly string $secret,
    ) {}

    public function nom(): string
    {
        return 'fake';
    }

    public function initialiser(DemandePaiement $demande): InitialisationPaiement
    {
        $this->enregistrer($demande->reference, [
            'statut' => StatutPaiementPasserelle::EnAttente->value,
            'montant' => $demande->montantFcfa,
            'moyen' => null,
        ]);

        return new InitialisationPaiement(
            reference: $demande->reference,
            urlPaiement: $demande->urlRetour,
            donnees: ['driver' => 'fake'],
        );
    }

    public function verifier(string $reference): VerificationPaiement
    {
        $etat = $this->lire($reference);

        if ($etat === null) {
            return new VerificationPaiement($reference, StatutPaiementPasserelle::EnAttente, null, null);
        }

        return new VerificationPaiement(
            reference: $reference,
            statut: StatutPaiementPasserelle::from((string) $etat['statut']),
            montantFcfa: isset($etat['montant']) ? (int) $etat['montant'] : null,
            moyenPaiement: isset($etat['moyen']) ? (string) $etat['moyen'] : null,
            donnees: $etat,
        );
    }

    public function signatureValide(Request $request): bool
    {
        $signature = (string) $request->header(self::ENTETE_SIGNATURE, '');

        return $signature !== '' && hash_equals($this->signer($request->getContent()), $signature);
    }

    public function referenceNotifiee(Request $request): ?string
    {
        $reference = $request->input('reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * Simule l'issue d'un paiement (guichet fake, tests).
     */
    public function simuler(string $reference, StatutPaiementPasserelle $statut, ?int $montantFcfa = null, string $moyen = 'orange'): void
    {
        $etat = $this->lire($reference) ?? [];

        $this->enregistrer($reference, [
            'statut' => $statut->value,
            'montant' => $montantFcfa ?? ($etat['montant'] ?? null),
            'moyen' => $moyen,
        ]);
    }

    public function signer(string $corps): string
    {
        return hash_hmac('sha256', $corps, $this->secret);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lire(string $reference): ?array
    {
        $chemin = $this->chemin($reference);

        if (! $this->disque->exists($chemin)) {
            return null;
        }

        $etat = json_decode((string) $this->disque->get($chemin), true);

        return is_array($etat) ? $etat : null;
    }

    /**
     * @param  array<string, mixed>  $etat
     */
    private function enregistrer(string $reference, array $etat): void
    {
        $this->disque->put($this->chemin($reference), (string) json_encode($etat));
    }

    private function chemin(string $reference): string
    {
        return self::DOSSIER.'/'.hash('sha256', $reference).'.json';
    }
}
