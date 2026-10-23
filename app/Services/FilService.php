<?php

namespace App\Services;

use App\Donnees\FiltresFil;
use App\Donnees\SalveFil;
use App\Enums\SousTypeService;
use App\Enums\TypeContrat;
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
        'faits_cles', 'image_url', 'image_largeur', 'image_hauteur', 'source', 'source_nom', 'source_url',
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
            ->when($filtres->sousType !== null, fn (Builder $requete) => $requete->where('sous_type', $filtres->sousType?->value))
            ->when($filtres->communeId !== null, fn (Builder $requete) => $requete->where('commune_id', $filtres->communeId))
            ->when($filtres->categorieIds !== [], fn (Builder $requete) => $requete->whereIn('categorie_id', $filtres->categorieIds))
            ->when($filtres->typeContrat !== null, fn (Builder $requete) => $requete->where('type_contrat', $filtres->typeContrat?->value))
            ->when($filtres->aujourdhui, fn (Builder $requete) => $requete->where('publiee_le', '>=', Carbon::today()))
            ->when($filtres->recherche !== null, fn (Builder $requete) => $this->rechercher($requete, (string) $filtres->recherche))
            ->orderByDesc('tri_le')
            ->orderByDesc('id');
    }

    /**
     * Offres d'emploi en ligne par type de contrat (colonne de droite), dans l'ordre de l'enum.
     * Les types sans offre sont omis.
     *
     * @return list<array{contrat: string, libelle: string, nombre: int}>
     */
    public function offresParContrat(): array
    {
        return $this->cache->compteurs('contrats', function (): array {
            $nombres = Publication::query()->publiees()
                ->where('type', TypePublication::Emploi->value)
                ->whereNotNull('type_contrat')
                ->selectRaw('type_contrat, count(*) as nombre')
                ->groupBy('type_contrat')
                ->toBase()
                ->pluck('nombre', 'type_contrat');

            $resultat = [];
            foreach (TypeContrat::cases() as $contrat) {
                $nombre = (int) ($nombres[$contrat->value] ?? 0);
                if ($nombre > 0) {
                    $resultat[] = ['contrat' => $contrat->value, 'libelle' => $contrat->libelle(), 'nombre' => $nombre];
                }
            }

            return $resultat;
        });
    }

    /**
     * Publications en ligne par catégorie principale (sous-catégories incluses), pour le menu des catégories.
     *
     * @return array<int, int> id de la catégorie principale => nombre
     */
    public function nombresParCategorie(): array
    {
        return $this->cache->compteurs('categories', function (): array {
            $categories = app(Referentiels::class)->categories();
            $nombres = Publication::query()->publiees()
                ->whereNotNull('categorie_id')
                ->selectRaw('categorie_id, count(*) as nombre')
                ->groupBy('categorie_id')
                ->toBase()
                ->pluck('nombre', 'categorie_id');

            $resultat = [];
            foreach ($nombres as $categorieId => $nombre) {
                $categorie = $categories[(int) $categorieId] ?? null;
                if ($categorie === null) {
                    continue;
                }
                $principale = $categorie['parent_id'] ?? $categorie['id'];
                $resultat[$principale] = ($resultat[$principale] ?? 0) + (int) $nombre;
            }

            return $resultat;
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
     * Dernières demandes de prestation en ligne, mises en avant dans le fil (D-069), et leur nombre.
     *
     * @return array{nombre: int, demandes: list<array{titre: string, slug: string, commune_id: ?int, publiee_le: ?string}>}
     */
    public function demandesRecentes(int $nombre = 3): array
    {
        return $this->cache->compteurs('demandes-recentes', function () use ($nombre): array {
            $requete = Publication::query()->publiees()
                ->where('type', TypePublication::Service->value)
                ->where('sous_type', SousTypeService::Demande->value);

            return [
                'nombre' => (clone $requete)->count(),
                'demandes' => $requete->orderByDesc('tri_le')->orderByDesc('id')
                    ->limit($nombre)
                    ->toBase()
                    ->get(['titre', 'slug', 'commune_id', 'publiee_le'])
                    ->map(fn (object $ligne): array => [
                        'titre' => (string) $ligne->titre,
                        'slug' => (string) $ligne->slug,
                        'commune_id' => $ligne->commune_id !== null ? (int) $ligne->commune_id : null,
                        'publiee_le' => $ligne->publiee_le !== null ? (string) $ligne->publiee_le : null,
                    ])
                    ->all(),
            ];
        });
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
