<?php

namespace App\Http\Requests;

use App\Donnees\FiltresFil;
use App\Enums\SousTypeService;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Services\Referentiels;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres du fil passés en paramètres d'URL. Une valeur inconnue (commune ou
 * catégorie supprimée, lien ancien) est ignorée plutôt que de casser la page.
 */
class FilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:20'],
            'commune' => ['nullable', 'string', 'max:120'],
            'categorie' => ['nullable', 'string', 'max:120'],
            'contrat' => ['nullable', 'string', 'max:20'],
            'sous_type' => ['nullable', 'string', 'max:20'],
            'aujourdhui' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'curseur' => ['nullable', 'string', 'max:500'],
            'depuis' => ['nullable', 'date'],
            'deja' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * Nombre de cartes déjà affichées par le client (emplacements sponsorisés, §14.9).
     */
    public function deja(): int
    {
        return max(0, min(100000, $this->integer('deja')));
    }

    public function filtres(?TypePublication $type = null, ?SousTypeService $sousType = null): FiltresFil
    {
        $referentiels = app(Referentiels::class);

        $type ??= TypePublication::tryFrom($this->string('type')->toString());
        // Offres ou demandes : seulement pour les services (une demande est toujours un service).
        $sousType ??= SousTypeService::tryFrom($this->string('sous_type')->toString());
        if ($sousType !== null && $type !== TypePublication::Formation && $type !== TypePublication::Emploi) {
            $type = TypePublication::Service;
        } else {
            $sousType = null;
        }
        $commune = $this->filled('commune') ? $referentiels->communeParSlug($this->string('commune')->toString()) : null;
        $categorie = $this->filled('categorie') ? $referentiels->categorieParSlug($this->string('categorie')->toString()) : null;

        // Une catégorie d'un autre type que l'onglet courant est ignorée.
        if ($categorie !== null && $type !== null && $categorie['type'] !== $type->value) {
            $categorie = null;
        }

        $contrat = TypeContrat::tryFrom($this->string('contrat')->toString());
        $recherche = trim(preg_replace('/\s+/u', ' ', $this->string('q')->toString()) ?? '');

        return new FiltresFil(
            type: $type,
            communeId: $commune['id'] ?? null,
            communeSlug: $commune['slug'] ?? null,
            categorieIds: $categorie !== null ? $referentiels->idsAvecSousCategories($categorie['id']) : [],
            categorieSlug: $categorie['slug'] ?? null,
            // Le type de contrat n'a de sens que pour les offres d'emploi.
            typeContrat: in_array($type, [null, TypePublication::Emploi], true) ? $contrat : null,
            aujourdhui: $this->boolean('aujourdhui'),
            recherche: $recherche !== '' ? $recherche : null,
            sousType: $sousType,
        );
    }

    public function curseur(): ?string
    {
        return $this->filled('curseur') ? $this->string('curseur')->toString() : null;
    }
}
