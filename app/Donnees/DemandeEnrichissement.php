<?php

namespace App\Donnees;

/**
 * Texte d'une publication soumis à l'IA pour réécriture et score de fiabilité.
 */
final readonly class DemandeEnrichissement
{
    /**
     * @param  list<string>  $categoriesPossibles
     */
    public function __construct(
        public string $type,
        public string $titre,
        public string $contenu,
        public ?string $entreprise = null,
        public ?string $lieu = null,
        public array $categoriesPossibles = [],
    ) {}
}
