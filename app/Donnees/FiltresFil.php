<?php

namespace App\Donnees;

use App\Enums\SousTypeService;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use Illuminate\Support\Carbon;

/**
 * Filtres du fil, déjà résolus (slugs → ids) et validés.
 */
final readonly class FiltresFil
{
    /**
     * @param  list<int>  $categorieIds  catégorie choisie et ses sous-catégories
     */
    public function __construct(
        public ?TypePublication $type = null,
        public ?int $communeId = null,
        public ?string $communeSlug = null,
        public array $categorieIds = [],
        public ?string $categorieSlug = null,
        public ?TypeContrat $typeContrat = null,
        public bool $aujourdhui = false,
        public ?string $recherche = null,
        // Offres ou demandes de service (« Demandes » : ceux qui cherchent un prestataire).
        public ?SousTypeService $sousType = null,
    ) {}

    /**
     * Partie de la clé de cache qui distingue les filtres secondaires.
     * « Aujourd'hui » dépend de la date : la clé change donc chaque jour.
     */
    public function empreinte(): string
    {
        return substr(md5((string) json_encode([
            $this->categorieIds,
            $this->typeContrat?->value,
            $this->aujourdhui ? Carbon::today()->toDateString() : null,
            $this->recherche !== null ? mb_strtolower($this->recherche) : null,
            $this->sousType?->value,
        ])), 0, 12);
    }

    /**
     * Paramètres de requête à conserver dans les liens (onglets, « Charger plus »…).
     *
     * @return array<string, string>
     */
    public function parametres(): array
    {
        return array_filter([
            'commune' => $this->communeSlug,
            'categorie' => $this->categorieSlug,
            'contrat' => $this->typeContrat?->value,
            'aujourdhui' => $this->aujourdhui ? '1' : null,
            'q' => $this->recherche,
            'sous_type' => $this->sousType?->value,
        ], fn (?string $valeur): bool => $valeur !== null && $valeur !== '');
    }

    /**
     * URL du fil avec certains filtres modifiés (null retire le filtre).
     * Changer de type retire la catégorie et le contrat, propres à un type ; le sous-type
     * (demandes de service) change avec l'onglet.
     *
     * @param  array<string, string|null>  $modifications
     */
    public function url(array $modifications = [], ?TypePublication $type = null, bool $changerType = false, ?SousTypeService $sousType = null): string
    {
        $typeCible = $changerType ? $type : $this->type;
        $sousTypeCible = $changerType ? $sousType : $this->sousType;
        $parametres = $this->parametres();
        unset($parametres['sous_type']);

        if ($changerType && $typeCible !== $this->type) {
            unset($parametres['categorie'], $parametres['contrat']);
        }

        // Les demandes ont leur propre page (/demandes) ; les offres seules se filtrent par paramètre.
        $demandes = $typeCible === TypePublication::Service && $sousTypeCible === SousTypeService::Demande;
        if ($typeCible === TypePublication::Service && $sousTypeCible === SousTypeService::Offre) {
            $parametres['sous_type'] = SousTypeService::Offre->value;
        }

        $parametres = array_filter(
            array_merge($parametres, $modifications),
            fn (?string $valeur): bool => $valeur !== null && $valeur !== '',
        );

        $route = match ($typeCible) {
            TypePublication::Emploi => 'fil.emplois',
            TypePublication::Service => $demandes ? 'fil.demandes' : 'fil.services',
            TypePublication::Formation => 'fil.formations',
            null => isset($parametres['q']) ? 'fil.recherche' : 'fil.index',
        };

        return route($route, $parametres);
    }

    /**
     * Un filtre secondaire est actif (le sous-type est porté par l'onglet « Demandes »).
     */
    public function estFiltre(): bool
    {
        return array_diff_key($this->parametres(), ['sous_type' => true]) !== [];
    }
}
