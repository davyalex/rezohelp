<?php

namespace App\Donnees;

/**
 * Réponse validée de l'IA.
 */
final readonly class ResultatEnrichissement
{
    /**
     * @param  list<string>  $signauxRisque
     * @param  bool  $appelExterne  faux pour un calcul local (driver « regles ») : rien à journaliser dans appels_ia
     */
    public function __construct(
        public string $texteCarte,
        public ?string $categorie,
        public int $scoreFiabilite,
        public array $signauxRisque,
        public string $modele,
        public int $tokensEntree = 0,
        public int $tokensSortie = 0,
        public int $dureeMs = 0,
        public bool $appelExterne = true,
    ) {}
}
