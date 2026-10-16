<?php

namespace App\Donnees;

use App\Enums\StatutPaiementPasserelle;

/**
 * Statut d'un paiement revérifié auprès de l'API de la passerelle.
 */
final readonly class VerificationPaiement
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function __construct(
        public string $reference,
        public StatutPaiementPasserelle $statut,
        public ?int $montantFcfa,
        public ?string $moyenPaiement,
        public array $donnees = [],
    ) {}

    public function estAccepte(): bool
    {
        return $this->statut === StatutPaiementPasserelle::Accepte;
    }
}
