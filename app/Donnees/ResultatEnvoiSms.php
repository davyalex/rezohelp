<?php

namespace App\Donnees;

/**
 * Résultat d'un envoi de SMS par un fournisseur.
 */
final readonly class ResultatEnvoiSms
{
    public function __construct(
        public bool $envoye,
        public ?string $reference = null,
        public int $coutFcfa = 0,
        public ?string $erreur = null,
    ) {}
}
