<?php

namespace App\Services;

use App\Enums\TypePublication;
use App\Models\Categorie;
use App\Models\Commune;

/**
 * Référentiels lus par le fil et les formulaires, mis en cache (ref:*) sous forme
 * de tableaux légers : aucune requête ni chargement paresseux dans les cartes.
 */
class Referentiels
{
    public function __construct(private readonly CacheFil $cache) {}

    /**
     * @return array<int, array{id: int, nom: string, slug: string}>
     */
    public function communes(): array
    {
        return $this->cache->referentiel('communes', fn (): array => Commune::query()->actives()
            ->get(['id', 'nom', 'slug'])
            ->mapWithKeys(fn (Commune $commune): array => [$commune->id => ['id' => $commune->id, 'nom' => $commune->nom, 'slug' => $commune->slug]])
            ->all());
    }

    /**
     * @return array<int, array{id: int, type: string, nom: string, slug: string, icone: ?string, parent_id: ?int}>
     */
    public function categories(): array
    {
        return $this->cache->referentiel('categories', fn (): array => Categorie::query()->actives()
            ->get(['id', 'type', 'nom', 'slug', 'icone', 'parent_id'])
            ->mapWithKeys(fn (Categorie $categorie): array => [$categorie->id => [
                'id' => $categorie->id,
                'type' => $categorie->type->value,
                'nom' => $categorie->nom,
                'slug' => $categorie->slug,
                'icone' => $categorie->icone,
                'parent_id' => $categorie->parent_id,
            ]])
            ->all());
    }

    public function nomCommune(?int $id): ?string
    {
        return $id === null ? null : ($this->communes()[$id]['nom'] ?? null);
    }

    public function nomCategorie(?int $id): ?string
    {
        return $id === null ? null : ($this->categories()[$id]['nom'] ?? null);
    }

    /**
     * @return array{id: int, nom: string, slug: string}|null
     */
    public function communeParSlug(string $slug): ?array
    {
        foreach ($this->communes() as $commune) {
            if ($commune['slug'] === $slug) {
                return $commune;
            }
        }

        return null;
    }

    /**
     * @return array{id: int, type: string, nom: string, slug: string, icone: ?string, parent_id: ?int}|null
     */
    public function categorieParSlug(string $slug): ?array
    {
        foreach ($this->categories() as $categorie) {
            if ($categorie['slug'] === $slug) {
                return $categorie;
            }
        }

        return null;
    }

    /**
     * La catégorie et ses sous-catégories (métiers).
     *
     * @return list<int>
     */
    public function idsAvecSousCategories(int $categorieId): array
    {
        $ids = [$categorieId];

        foreach ($this->categories() as $categorie) {
            if ($categorie['parent_id'] === $categorieId) {
                $ids[] = $categorie['id'];
            }
        }

        return $ids;
    }

    /**
     * Catégories principales d'un type (sans les sous-catégories).
     *
     * @return list<array{id: int, type: string, nom: string, slug: string, icone: ?string, parent_id: ?int}>
     */
    public function categoriesPrincipales(?TypePublication $type): array
    {
        return array_values(array_filter(
            $this->categories(),
            fn (array $categorie): bool => $categorie['parent_id'] === null && ($type === null || $categorie['type'] === $type->value),
        ));
    }
}
