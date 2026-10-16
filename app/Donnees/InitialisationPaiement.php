<?php

namespace App\Donnees;

/**
 * Réponse de la passerelle à l'ouverture d'un paiement.
 */
final readonly class InitialisationPaiement
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function __construct(
        public string $reference,
        public string $urlPaiement,
        public array $donnees = [],
    ) {}
}
