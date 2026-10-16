<?php

namespace App\Donnees;

/**
 * Ce qu'on transmet à la passerelle pour ouvrir un paiement.
 */
final readonly class DemandePaiement
{
    public function __construct(
        public string $reference,
        public int $montantFcfa,
        public string $description,
        public string $urlRetour,
        public string $urlNotification,
        public string $telephoneClient,
        public string $nomClient,
    ) {}
}
