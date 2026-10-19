<?php

namespace App\Services;

use App\Donnees\FiltresFil;
use App\Donnees\SalveFil;
use App\Enums\TypePublication;
use App\Models\Publication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Carbon;

/**
 * Lecture du fil par curseur (CONTEXTE §8) : tri tri_le DESC, id DESC, jamais d'OFFSET.
 * Seules les colonnes dénormalisées de publications sont lues pour les cartes.
 */
class FilService
{
    public const int TAILLE_SALVE = 12;

    public const string PARAMETRE_CURSEUR = 'curseur';

    /**
     * Colonnes lues pour afficher une carte.
     *
     * @var list<string>
     */
    public const array COLONNES_CARTE = [
        'id', 'type', 'sous_type', 'statut', 'titre', 'slug', 'extrait', 'categorie_id', 'commune_id',
        'auteur_id', 'entreprise_id', 'auteur_nom_affiche', 'auteur_avatar_url', 'auteur_verifie', 'auteur_badge',
        'faits_cles', 'image_url', 'image_largeur', 'image_hauteur', 'source', 'source_nom',
        'nb_jaime', 'nb_commentaires', 'publiee_le', 'tri_le', 'code_court', 'modifie_le',
    ];

    public function __construct(private readonly CacheFil $cache) {}

    public function salve(FiltresFil $filtres, ?string $curseur = null): SalveFil
    {
        /** @var array{ids: list<int>, suivant: ?string} $resultat */
        $resultat = $this->cache->salve(
            $filtres->type->value ?? 'tout',
            $filtres->communeSlug ?? 'toutes',
            $filtres->empreinte(),
            $curseur,
            fn (): array => $this->calculer($filtres, $curseur),
        );

        $publications = Publication::query()
            ->select(self::COLONNES_CARTE)
            ->whereIn('id', $resultat['ids'])
            ->get()
            ->sortBy(fn (Publication $publication): int|false => array_search($publication->id, $resultat['ids'], true))
            ->values();

        return new SalveFil($publications, $resultat['suivant']);
    }

    /**
     * Nombre de publications parues après la première carte affichée (bandeau « N nouvelles »).
     */
    public function nouvelles(FiltresFil $filtres, CarbonInterface $depuis): int
    {
        return $this->requete($filtres)->where('tri_le', '>', $depuis)->count();
    }

    /**
     * @return Builder<Publication>
     */
    public function requete(FiltresFil $filtres): Builder
    {
        return Publication::query()
            ->publiees()
            ->when($filtres->type !== null, fn (Builder $requete) => $requete->where('type', $filtres->type?->value))
            ->when($filtres->communeId !== null, fn (Builder $requete) => $requete->where('commune_id', $filtres->communeId))
            ->when($filtres->categorieIds !== [], fn (Builder $requete) => $requete->whereIn('categorie_id', $filtres->categorieIds))
            ->when($filtres->typeContrat !== null, fn (Builder $requete) => $requete->where('type_contrat', $filtres->typeContrat?->value))
            ->when($filtres->aujourdhui, fn (Builder $requete) => $requete->where('publiee_le', '>=', Carbon::today()))
            ->when($filtres->recherche !== null, fn (Builder $requete) => $this->rechercher($requete, (string) $filtres->recherche))
            ->orderByDesc('tri_le')
            ->orderByDesc('id');
    }

    /**
     * Communes qui ont le plus de publications en ligne (colonne de droite).
     *
     * @return list<array{id: int, nom: string, slug: string, nombre: int}>
     */
    public function communesPopulaires(int $nombre = 6): array
    {
        return $this->cache->compteurs('communes', function () use ($nombre): array {
            $referentiel = app(Referentiels::class)->communes();

            return Publication::query()->publiees()->whereNotNull('commune_id')
                ->selectRaw('commune_id, count(*) as nombre')
                ->groupBy('commune_id')
                ->orderByDesc('nombre')
                ->limit($nombre)
                ->toBase()
                ->get()
                ->filter(fn (object $ligne): bool => isset($referentiel[(int) $ligne->commune_id]))
                ->map(fn (object $ligne): array => [...$referentiel[(int) $ligne->commune_id], 'nombre' => (int) $ligne->nombre])
                ->values()
                ->all();
        });
    }

    /**
     * Dernières formations en ligne (colonne de droite).
     *
     * @return list<array{titre: string, slug: string}>
     */
    public function formationsRecentes(int $nombre = 3): array
    {
        return $this->cache->compteurs('formations-recentes', fn (): array => Publication::query()->publiees()
            ->where('type', TypePublication::Formation->value)
            ->orderByDesc('tri_le')->orderByDesc('id')
            ->limit($nombre)
            ->toBase()
            ->get(['titre', 'slug'])
            ->map(fn (object $ligne): array => ['titre' => (string) $ligne->titre, 'slug' => (string) $ligne->slug])
            ->all());
    }

    /**
     * Nombre de résultats d'une recherche (journal des recherches).
     */
    public function compter(FiltresFil $filtres): int
    {
        return $this->requete($filtres)->reorder()->count();
    }

    /**
     * @return array{ids: list<int>, suivant: ?string}
     */
    private function calculer(FiltresFil $filtres, ?string $curseur): array
    {
        $page = $this->requete($filtres)->cursorPaginate(
            self::TAILLE_SALVE,
            ['id', 'tri_le'],
            self::PARAMETRE_CURSEUR,
            $curseur !== null ? Cursor::fromEncoded($curseur) : null,
        );

        return [
            'ids' => array_values(array_map(fn (Publication $publication): int => $publication->id, $page->items())),
            'suivant' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Recherche plein texte (FULLTEXT titre + contenu, mode booléen, préfixes).
     * Les mots de moins de 3 lettres sont ignorés par MySQL : repli sur le titre.
     *
     * @param  Builder<Publication>  $requete
     */
    private function rechercher(Builder $requete, string $recherche): void
    {
        $mots = array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($recherche)) ?: [],
            fn (string $mot): bool => mb_strlen($mot) >= 3,
        );

        if ($mots === []) {
            $requete->where('titre', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $recherche).'%');

            return;
        }

        $requete->whereFullText(['titre', 'contenu'], implode(' ', array_map(fn (string $mot): string => '+'.$mot.'*', $mots)), ['mode' => 'boolean']);
    }
}
