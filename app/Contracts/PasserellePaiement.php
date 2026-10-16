<?php

namespace App\Contracts;

use App\Donnees\DemandePaiement;
use App\Donnees\InitialisationPaiement;
use App\Donnees\VerificationPaiement;
use Illuminate\Http\Request;

/**
 * Passerelle de paiement (drivers : cinetpay, fake).
 *
 * Un paiement n'est jamais considéré comme réussi sur la seule foi de la
 * redirection ou du webhook : on revérifie toujours avec verifier().
 */
interface PasserellePaiement
{
    public function nom(): string;

    public function initialiser(DemandePaiement $demande): InitialisationPaiement;

    public function verifier(string $reference): VerificationPaiement;

    public function signatureValide(Request $request): bool;

    /**
     * Référence de la transaction contenue dans une notification (webhook).
     */
    public function referenceNotifiee(Request $request): ?string;
}
